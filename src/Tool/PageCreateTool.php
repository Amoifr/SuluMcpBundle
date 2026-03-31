<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageCreateTool
{
    use HandleTrait;

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
        name: 'sulu_page_create',
        description: 'Create a new page in a webspace. Workflow: 1) Call sulu_get_context to discover templates and their fields. 2) Call sulu_page_tree to find the parentId (UUID of the parent page under which this page should be created). 3) Choose a template key (e.g. "default") and pass its field values in "content" as a flat object: content={"article": "<p>HTML here</p>"}. The "title" is a separate parameter — do not repeat it in content. The "url" is auto-generated from the title if omitted. The page is created as a draft — call sulu_page_publish afterward to make it live.',
    )]
    public function createPage(
        string $webspace,
        string $locale,
        string $template,
        string $title,
        string $parentId,
        ?string $url = null,
        #[Schema(type: 'object', description: 'Template field values as key-value pairs, e.g. {"article": "<p>HTML content</p>"}', additionalProperties: true)]
        ?array $content = null,
    ): array {
        try {
            $data = \array_merge(null !== $content ? PageUpdateTool::normalizeContent($content) : [], [
                'locale' => $locale,
                'template' => $template,
                'title' => $title,
                'url' => $url ?? '/'.\mb_strtolower(\str_replace(' ', '-', $title)),
            ]);

            $message = new CreatePageMessage($webspace, $parentId, $data);

            /** @var PageInterface $page */
            $page = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($page, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            return [
                'success' => true,
                'uuid' => $page->getUuid(),
                'data' => $normalized,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create page "%s" in webspace "%s": %s', $title, $webspace, $e->getMessage()),
                'hint' => 'Verify the template key exists (use sulu_get_context), parentId is a valid page UUID (use sulu_page_tree), and content fields match the template schema.',
            ];
        }
    }
}
