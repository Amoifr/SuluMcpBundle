<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageUnpublishTool
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
        name: 'sulu_page_unpublish',
        description: 'Unpublish a live page — removes it from the website but keeps the draft. The page content is preserved and can be re-published later with sulu_page_publish. Use this to take content offline without deleting it.',
    )]
    public function unpublishPage(string $uuid, string $locale): array
    {
        try {
            $message = new ApplyWorkflowTransitionPageMessage(
                ['uuid' => $uuid],
                $locale,
                'unpublish',
            );

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'action' => 'unpublished',
                'locale' => $locale,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to unpublish page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the page exists and is currently published. Use sulu_page_get to check the current workflowPlace.',
            ];
        }
    }
}
