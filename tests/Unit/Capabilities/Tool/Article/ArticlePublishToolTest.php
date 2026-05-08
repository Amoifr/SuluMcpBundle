<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticlePublishTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticlePublishTool::class)]
final class ArticlePublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticlePublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new ArticlePublishTool($this->messageBus);
    }

    public function testPublishArticleReturnsSuccess(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]));

        $result = $this->tool->publishArticle('test-uuid', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('test-uuid', $result['uuid']);
        $this->assertSame('published', $result['action']);
        $this->assertSame('en', $result['locale']);
    }

    public function testPublishArticleReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Workflow error'));

        $result = $this->tool->publishArticle('uuid', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('uuid', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticlePublishTool::class, 'publishArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_article_publish', $attributes[0]->newInstance()->name);
    }
}
