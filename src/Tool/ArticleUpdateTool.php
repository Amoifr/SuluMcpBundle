<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ArticleRepositoryInterface $articleRepository,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_update',
        description: 'Update an existing article. Reads the current article state, merges your changes, and writes back -- so you only need to pass the fields you want to change. Pass template-specific field values in "content" as a flat object: content={"article": "<p>Updated HTML</p>"}. You can update title and template as separate parameters. The article stays in draft state after updating -- call sulu_article_publish to make changes live.',
    )]
    public function updateArticle(
        string $uuid,
        string $locale,
        ?string $title = null,
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"article": "<p>HTML content</p>"}', additionalProperties: true)]
        ?array $content = null,
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
                $data = \array_merge($data, PageUpdateTool::normalizeContent($content));
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
                'data' => $normalized,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update article %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists (use sulu_article_get) and content fields match the template schema (use sulu_get_context). Pass content as a flat object: content={"article": "<p>...</p>"}.',
            ];
        }
    }
}
