<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleCreateTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleCreateTool::class)]
final class ArticleCreateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new ArticleCreateTool($this->messageBus, $this->contentManager);
    }

    public function testCreateArticleDispatchesCreateArticleMessage(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('article-uuid-123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreateArticleMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test Article']);

        $result = $this->tool->createArticle('en', 'blog', 'Test Article');

        $this->assertTrue($result['success']);
        $this->assertSame('article-uuid-123', $result['uuid']);
    }

    public function testCreateArticleIncludesTypeInData(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $capturedMessage = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockArticle, &$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp($mockArticle, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createArticle('en', 'blog', 'Test', 'default');

        $this->assertInstanceOf(CreateArticleMessage::class, $capturedMessage);
    }

    public function testCreateArticleMergesContentIntoData(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockArticle, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $result = $this->tool->createArticle(
            'en',
            'blog',
            'Test',
            null,
            ['article' => '<p>Content</p>'],
        );

        $this->assertTrue($result['success']);
    }

    public function testCreateArticleResolvesAndNormalizesResult(): void
    {
        $mockArticle = $this->createMock(ArticleInterface::class);
        $mockArticle->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockArticle, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->expects($this->once())
            ->method('resolve')
            ->with($mockArticle, [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ])
            ->willReturn($mockDimensionContent);

        $this->contentManager->expects($this->once())
            ->method('normalize')
            ->with($mockDimensionContent)
            ->willReturn(['title' => 'Resolved Title']);

        $result = $this->tool->createArticle('en', 'blog', 'Test');

        $this->assertSame(['title' => 'Resolved Title'], $result['data']);
    }

    public function testCreateArticleReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Article creation failed'));

        $result = $this->tool->createArticle('en', 'blog', 'Test');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Article creation failed', $result['error']);
        $this->assertArrayHasKey('hint', $result);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreateArticleMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleCreateTool::class, 'createArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createArticle() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_article_create', $instance->name);
    }
}
