<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleUnpublishTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleUnpublishTool::class)]
final class ArticleUnpublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticleUnpublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new ArticleUnpublishTool($this->messageBus);
    }

    public function testUnpublishArticleReturnsSuccess(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]));

        $result = $this->tool->unpublishArticle('test-uuid', 'de');

        $this->assertTrue($result['success']);
        $this->assertSame('test-uuid', $result['uuid']);
        $this->assertSame('unpublished', $result['action']);
        $this->assertSame('de', $result['locale']);
    }

    public function testUnpublishArticleReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Not published'));

        $result = $this->tool->unpublishArticle('uuid', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('uuid', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleUnpublishTool::class, 'unpublishArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_article_unpublish', $attributes[0]->newInstance()->name);
    }
}
