<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Block\BlockDataValidator;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentMetadataMapper;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class ArticleUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly BlockDataValidator $blockDataValidator,
        private readonly BlockIdGeneratorInterface $blockIdGenerator,
        private readonly ContentMetadataMapper $contentMetadataMapper,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     * @param array<string, mixed>|null $excerpt
     * @param array<string, mixed>|null $seo
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_update',
        description: 'Update an existing article. Reads the current article state, merges your changes, and writes back -- so you only need to pass the fields you want to change. Pass template-specific field values in "content" as a flat object: content={"article": "<p>Updated HTML</p>"}. Content may also include a full "blocks" tree (nested blocks allowed) to replace the block content in one call — block _ids are assigned automatically and unknown block fields are rejected before saving. To change routing, pass either content={"url": "/path"} (simple route templates) or content={"page": {"path": "/blog", "uuid": "<parent-uuid>", "suffix": "slug"}} (page_tree_route templates) -- the wrong form is rejected here instead of failing inside Sulu. You can update title and template as separate parameters. The article stays in draft state after updating -- call sulu_content_publish (type: article) to make changes live.',
    )]
    public function updateArticle(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"article": "<p>HTML content</p>"}. To change the URL, pass {"url": "/path"} or {"page": {"path": "/blog", "uuid": "<parent-page-uuid>", "suffix": "my-article"}} matching the template\'s route property type.', additionalProperties: true)]
        ?array $content = null,
        #[Schema(type: 'object', description: 'Optional excerpt/teaser fields keyed by the project\'s excerpt field names (e.g. title, description, more, image, icon, excerptCategories, excerptTags). Media fields take {"id": <mediaId>}. Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $excerpt = null,
        #[Schema(type: 'object', description: 'Optional SEO fields keyed by the project\'s SEO field names (e.g. title, description, keywords, canonicalUrl, seoNoIndex, seoNoFollow, seoHideInSitemap). Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $seo = null,
    ): array {
        try {
            // Read current article state to get template and existing content
            $article = $this->articleRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true,
                ],
            );

            $currentDimensionContent = $this->contentManager->resolve($article, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $currentData = $this->contentManager->normalize($currentDimensionContent);

            // Build update data: start with current state, overlay user changes
            $data = \array_merge(
                $currentData,
                ['locale' => $locale],
            );

            if (null !== $title) {
                $data['title'] = $title;
            }
            if (null !== $template) {
                $data['template'] = $template;
            }
            if (null !== $content) {
                $normalizedContent = self::normalizeContent($content);
                if ($validationError = ArticleRouteValidator::validate($normalizedContent, required: false)) {
                    return $validationError;
                }
                $suluContent = ArticleRouteValidator::normalizeForSulu($normalizedContent);
                $templateKey = isset($data['template']) && \is_string($data['template']) ? $data['template'] : null;
                if ($blockError = $this->blockDataValidator->validateContentTree($suluContent, 'article', $templateKey)) {
                    return $blockError;
                }
                $suluContent = $this->assignBlockIds($suluContent, $this->blockIdGenerator);
                $data = \array_merge($data, $suluContent);
            }

            $data = $this->contentMetadataMapper->applyExcerpt($data, $excerpt, $locale);
            if (isset($data['error'])) {
                return $data;
            }
            $data = $this->contentMetadataMapper->applySeo($data, $seo, $locale);
            if (isset($data['error'])) {
                return $data;
            }

            // Ensure all array keys are strings (Sulu's MetadataResolver requires string keys)
            $data = $this->stringifyKeys($data);

            $message = new ModifyArticleMessage(['uuid' => $uuid], $data);

            /** @var ArticleInterface $updatedArticle */
            $updatedArticle = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($updatedArticle, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            return [
                'success' => true,
                'uuid' => $updatedArticle->getUuid(),
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update article %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_article_get) and content fields match the template schema (use sulu_get_context). Pass content as a flat object: content={"article": "<p>...</p>"}.',
            ];
        }
    }
}
