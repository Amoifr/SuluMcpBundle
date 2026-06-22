<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Content;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class ContentDeleteTool
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentTypeResolver $contentTypeResolver,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_content_delete',
        description: 'Permanently delete a page, article, or snippet by UUID. Set "type" to "page", "article", or "snippet". Removes both draft and published versions — this cannot be undone. For pages with children, set forceRemoveChildren=true to delete the whole subtree (ignored for articles/snippets). Snippets may be referenced by other content; deleting one removes that shared content everywhere it is used.',
    )]
    public function deleteContent(
        string $type,
        string $uuid,
        string $locale,
        bool $forceRemoveChildren = false,
    ): array {
        if (!$this->contentTypeResolver->supports($type)) {
            return [
                'error' => \sprintf('Unsupported content type "%s".', $type),
                'hint' => \sprintf('Supported types: %s.', \implode(', ', $this->contentTypeResolver->supportedTypes())),
            ];
        }

        try {
            $message = $this->contentTypeResolver->createRemoveMessage($type, $uuid, $locale, $forceRemoveChildren);

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'type' => $type,
                'uuid' => $uuid,
                'deleted' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete %s %s: %s', $type, $uuid, $e->getMessage()),
                'hint' => \sprintf('Verify the UUID exists (use sulu_%s_get). For a page with children, set forceRemoveChildren=true.', $type),
            ];
        }
    }
}
