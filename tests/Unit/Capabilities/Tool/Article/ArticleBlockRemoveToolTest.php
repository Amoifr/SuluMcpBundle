<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleBlockRemoveTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleBlockRemoveTool::class)]
final class ArticleBlockRemoveToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleBlockRemoveTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new ArticleBlockRemoveTool($this->messageBus, $this->articleRepository, $this->contentManager);
    }

    public function testRemoveBlockReturnsSuccess(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('uuid');

        $this->articleRepository->method('getOneBy')->willReturn($article);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'blog',
            'title' => 'Test',
            'blocks' => [['type' => 'text'], ['type' => 'image']],
        ]);

        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($updatedArticle, 'handler')]));

        $result = $this->tool->removeBlock('uuid', 'en', 'blocks', 0);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['removedIndex']);
        $this->assertSame(1, $result['blockCount']);
    }

    public function testRemoveBlockReturnsErrorForOutOfRange(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $dimensionContent = $this->createMock(DimensionContentInterface::class);

        $this->articleRepository->method('getOneBy')->willReturn($article);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['blocks' => [['type' => 'text']]]);

        $result = $this->tool->removeBlock('uuid', 'en', 'blocks', 5);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('out of range', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleBlockRemoveTool::class, 'removeBlock');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_article_block_remove', $attributes[0]->newInstance()->name);
    }
}
