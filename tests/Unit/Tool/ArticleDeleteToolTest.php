<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Message\RemoveArticleMessage;
use Sulu\McpServerBundle\Tool\ArticleDeleteTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleDeleteTool::class)]
final class ArticleDeleteToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticleDeleteTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new ArticleDeleteTool($this->messageBus);
    }

    public function testDeleteArticleDispatchesRemoveArticleMessage(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(RemoveArticleMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deleteArticle('article-uuid-123', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('article-uuid-123', $result['uuid']);
        $this->assertTrue($result['deleted']);
    }

    public function testDeleteArticleReturnsSuccessResponse(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp(null, 'handler')));

        $result = $this->tool->deleteArticle('test-uuid', 'de');

        $this->assertSame([
            'success' => true,
            'uuid' => 'test-uuid',
            'deleted' => true,
        ], $result);
    }

    public function testDeleteArticleReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Cannot delete article'));

        $result = $this->tool->deleteArticle('uuid-1', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Cannot delete article', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testDeleteArticleMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleDeleteTool::class, 'deleteArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'deleteArticle() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_article_delete', $instance->name);
    }
}
