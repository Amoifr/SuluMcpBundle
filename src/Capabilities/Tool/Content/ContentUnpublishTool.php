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
class ContentUnpublishTool
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
        name: 'sulu_content_unpublish',
        description: 'Unpublish a live page, article, or snippet — removes it from the website but keeps the draft. Set "type" to "page", "article", or "snippet". The content is preserved and can be re-published later with sulu_content_publish. Use this to take content offline without deleting it.',
    )]
    public function unpublishContent(string $type, string $uuid, string $locale): array
    {
        if (!$this->contentTypeResolver->supports($type)) {
            return [
                'error' => \sprintf('Unsupported content type "%s".', $type),
                'hint' => \sprintf('Supported types: %s.', \implode(', ', $this->contentTypeResolver->supportedTypes())),
            ];
        }

        try {
            $message = $this->contentTypeResolver->createTransitionMessage($type, $uuid, $locale, 'unpublish');

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'type' => $type,
                'uuid' => $uuid,
                'action' => 'unpublished',
                'locale' => $locale,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to unpublish %s %s: %s', $type, $uuid, $e->getMessage()),
                'hint' => \sprintf('Verify the content exists and is currently published (use sulu_%s_get to check workflowPlace).', $type),
            ];
        }
    }
}
