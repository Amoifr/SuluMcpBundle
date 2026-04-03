<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\RemoveArticleMessage;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleDeleteTool
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
        name: 'sulu_article_delete',
        description: 'Permanently delete an article by UUID. Removes both draft and published versions. This action cannot be undone.',
    )]
    public function deleteArticle(
        string $uuid,
        string $locale,
    ): array {
        try {
            $message = new RemoveArticleMessage(['uuid' => $uuid], $locale);

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'uuid' => $uuid,
                'deleted' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete article %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the article UUID exists (use sulu_article_get).',
            ];
        }
    }
}
