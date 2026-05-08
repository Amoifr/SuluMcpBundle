<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleUnpublishTool
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
        name: 'sulu_article_unpublish',
        description: 'Unpublish a live article — removes it from the website but keeps the draft. The article content is preserved and can be re-published later with sulu_article_publish. Use this to take content offline without deleting it.',
    )]
    public function unpublishArticle(string $uuid, string $locale): array
    {
        try {
            $message = new ApplyWorkflowTransitionArticleMessage(
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
                'error' => \sprintf('Failed to unpublish article %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the article exists and is currently published. Use sulu_article_get to check the current workflowPlace.',
            ];
        }
    }
}
