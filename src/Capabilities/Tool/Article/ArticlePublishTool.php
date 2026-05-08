<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticlePublishTool
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
        name: 'sulu_article_publish',
        description: 'Publish an article to make it visible on the website. Takes the current draft content and makes it the live version. Articles are always created/updated as drafts first — call this after sulu_article_create or sulu_article_update to go live. IMPORTANT: Always ask the user for confirmation before calling this tool — never publish without explicit user approval.',
    )]
    public function publishArticle(string $uuid, string $locale): array
    {
        try {
            $message = new ApplyWorkflowTransitionArticleMessage(
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
                'error' => \sprintf('Failed to publish article %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the article exists and is in draft state. Use sulu_article_get to check the current workflowPlace.',
            ];
        }
    }
}
