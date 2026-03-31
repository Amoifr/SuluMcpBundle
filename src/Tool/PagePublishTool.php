<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PagePublishTool
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_publish',
        description: 'Publish a page to make it visible on the website. Takes the current draft content and makes it the live version. Pages are always created/updated as drafts first — call this after sulu_page_create or sulu_page_update to go live. Can be called multiple times to re-publish after edits. IMPORTANT: Always ask the user for confirmation before calling this tool — never publish without explicit user approval.',
    )]
    public function publishPage(string $uuid, string $locale): array
    {
        try {
            $message = new ApplyWorkflowTransitionPageMessage(
                ['uuid' => $uuid],
                $locale,
                'publish',
            );

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'action' => 'published',
                'locale' => $locale,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to publish page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the page exists and is in draft state. Use sulu_page_get to check the current workflowPlace.',
            ];
        }
    }
}
