<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\BlockDataNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageUpdateTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleCreateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_create',
        description: 'Create a new article (draft). Workflow: 1) Call sulu_get_context to discover article templates and their fields. 2) Choose a template key (e.g. "blog") and pass its field values in "content" as a flat object: content={"article": "<p>HTML here</p>"}. The "title" is a separate parameter -- do not repeat it in content. IMPORTANT: articles need URL routing data, and the form depends on the template field type. If the template has a property of type "route", pass content={"url": "/my-article"}. If the template has a property of type "page_tree_route" (most blog templates), pass content={"page": {"path": "/blog", "uuid": "<parent-page-uuid>", "suffix": "my-article"}}. The wrong form is rejected here (so you do not get a silent url=null). After create, call sulu_article_publish to make it live.',
    )]
    public function createArticle(
        string $locale,
        string $template,
        string $title,
        ?string $type = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"article": "<p>HTML content</p>"}. Must include URL routing data: either {"url": "/path"} for simple route templates, or {"page": {"path", "uuid", "suffix"}} for page_tree_route templates.', additionalProperties: true)]
        ?array $content = null,
    ): array {
        try {
            $normalizedContent = null !== $content ? PageUpdateTool::normalizeContent($content) : [];

            if ($validationError = ArticleRouteValidator::validate($normalizedContent, required: true)) {
                return $validationError;
            }

            $data = \array_merge($normalizedContent, [
                'locale' => $locale,
                'template' => $template,
                'title' => $title,
            ]);

            if (null !== $type) {
                $data['type'] = $type;
            }

            // Ensure all array keys are strings (Sulu's MetadataResolver requires string keys)
            $data = $this->stringifyKeys($data);

            $message = new CreateArticleMessage($data);

            /** @var ArticleInterface $article */
            $article = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($article, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            if ($postCheckError = ArticleRouteValidator::assertRoutingResolved($normalized, $normalizedContent)) {
                $postCheckError['uuid'] = $article->getUuid();

                return $postCheckError;
            }

            return [
                'success' => true,
                'uuid' => $article->getUuid(),
                'data' => $normalized,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create article "%s": %s', $title, $e->getMessage()),
                'hint' => 'Verify the template key exists (use sulu_get_context) and content fields match the template schema.',
            ];
        }
    }
}
