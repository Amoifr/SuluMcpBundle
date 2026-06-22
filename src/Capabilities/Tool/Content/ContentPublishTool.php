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
class ContentPublishTool
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
        name: 'sulu_content_publish',
        description: 'Publish a page, article, or snippet to make its current draft the live version. Set "type" to "page", "article", or "snippet". Content is always created/updated as a draft first — call this after creating or updating to go live. Can be called again to re-publish after edits. IMPORTANT: Always ask the user for confirmation before calling this tool — never publish without explicit user approval.',
    )]
    public function publishContent(string $type, string $uuid, string $locale): array
    {
        if (!$this->contentTypeResolver->supports($type)) {
            return [
                'error' => \sprintf('Unsupported content type "%s".', $type),
                'hint' => \sprintf('Supported types: %s.', \implode(', ', $this->contentTypeResolver->supportedTypes())),
            ];
        }

        try {
            $message = $this->contentTypeResolver->createTransitionMessage($type, $uuid, $locale, 'publish');

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'type' => $type,
                'uuid' => $uuid,
                'action' => 'published',
                'locale' => $locale,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to publish %s %s: %s', $type, $uuid, $e->getMessage()),
                'hint' => \sprintf('Verify the content exists and is in draft state (use sulu_%s_get to check workflowPlace).', $type),
            ];
        }
    }
}
