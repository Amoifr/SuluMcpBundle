<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\RemovePageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageDeleteTool
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
        name: 'sulu_page_delete',
        description: 'Permanently delete a page by UUID. Removes both draft and published versions. Fails if the page has child pages unless forceRemoveChildren=true, which deletes the entire subtree. This action cannot be undone.',
    )]
    public function deletePage(
        string $uuid,
        string $locale,
        bool $forceRemoveChildren = false,
    ): array {
        try {
            $message = new RemovePageMessage(['uuid' => $uuid], $locale, $forceRemoveChildren);

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'deleted' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete page %s: %s', $uuid, $e->getMessage()),
                'hint' => 'If the page has children, set forceRemoveChildren=true to delete the entire subtree.',
            ];
        }
    }
}
