<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Tool\ArticleUpdateTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleUpdateTool::class)]
final class ArticleUpdateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ArticleUpdateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->tool = new ArticleUpdateTool($this->messageBus, $this->contentManager, $this->articleRepository);
    }

    public function testUpdateArticleReadsCurrentStateMergesAndDispatches(): void
    {
        $currentArticle = $this->createMock(ArticleInterface::class);
        $currentArticle->method('getUuid')->willReturn('uuid-1');
        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('uuid-1');

        $this->articleRepository->method('getOneBy')->willReturn($currentArticle);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Old Title', 'template' => 'blog']);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($updatedArticle) {
                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($updatedArticle, 'handler'));
            });

        $result = $this->tool->updateArticle('uuid-1', 'en', 'New Title');

        $this->assertTrue($result['success']);
        $this->assertSame('uuid-1', $result['uuid']);
    }

    public function testUpdateArticleMergesContentOverCurrentData(): void
    {
        $currentArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('uuid-1');

        $this->articleRepository->method('getOneBy')->willReturn($currentArticle);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Old', 'article' => '<p>Old</p>']);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($updatedArticle, 'handler')));

        $result = $this->tool->updateArticle('uuid-1', 'en', null, null, ['article' => '<p>New</p>']);

        $this->assertTrue($result['success']);
    }

    public function testUpdateArticleReturnsErrorOnException(): void
    {
        $this->articleRepository->method('getOneBy')
            ->willThrowException(new \RuntimeException('Article not found'));

        $result = $this->tool->updateArticle('uuid-1', 'en', 'Title');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Article not found', $result['error']);
        $this->assertArrayHasKey('hint', $result);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testUpdateArticleMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleUpdateTool::class, 'updateArticle');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'updateArticle() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_article_update', $instance->name);
    }
}
