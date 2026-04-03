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
use Sulu\McpServerBundle\Tool\ArticleBlockAddTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleBlockAddTool::class)]
final class ArticleBlockAddToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleBlockAddTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new ArticleBlockAddTool($this->messageBus, $this->articleRepository, $this->contentManager);
    }

    public function testAddBlockAppendsBlockAndReturnsSuccess(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('article-uuid');

        $this->articleRepository->method('getOneBy')->willReturn($article);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'blog',
            'title' => 'Test',
            'blocks' => [['type' => 'text', 'text' => 'existing']],
        ]);

        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($updatedArticle, 'handler')]));

        $result = $this->tool->addBlock('article-uuid', 'en', 'image', 'blocks', ['src' => '/img.jpg']);

        $this->assertTrue($result['success']);
        $this->assertSame('article-uuid', $result['uuid']);
        $this->assertSame(2, $result['blockCount']);
        $this->assertSame(1, $result['addedAt']);
    }

    public function testAddBlockUsesModifyArticleMessage(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('uuid');

        $this->articleRepository->method('getOneBy')->willReturn($article);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['template' => 'default', 'title' => 'T', 'blocks' => []]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($updatedArticle, 'handler')]));

        $this->tool->addBlock('uuid', 'en', 'text', 'blocks');
    }

    public function testAddBlockReturnsErrorOnException(): void
    {
        $this->articleRepository->method('getOneBy')
            ->willThrowException(new \RuntimeException('Not found'));

        $result = $this->tool->addBlock('bad-uuid', 'en', 'text', 'blocks');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('bad-uuid', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleBlockAddTool::class, 'addBlock');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_article_block_add', $attributes[0]->newInstance()->name);
    }
}
