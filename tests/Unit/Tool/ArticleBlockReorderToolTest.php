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
use Sulu\McpServerBundle\Tool\ArticleBlockReorderTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ArticleBlockReorderTool::class)]
final class ArticleBlockReorderToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private ArticleBlockReorderTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new ArticleBlockReorderTool($this->messageBus, $this->articleRepository, $this->contentManager);
    }

    public function testReorderBlocksReturnsSuccess(): void
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
            'blocks' => [['type' => 'a'], ['type' => 'b'], ['type' => 'c']],
        ]);

        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($updatedArticle, 'handler')]));

        $result = $this->tool->reorderBlocks('uuid', 'en', 'blocks', [2, 0, 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['blockCount']);
        $this->assertSame([2, 0, 1], $result['order']);
    }

    public function testReorderBlocksReturnsErrorForMismatchedLength(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $dimensionContent = $this->createMock(DimensionContentInterface::class);

        $this->articleRepository->method('getOneBy')->willReturn($article);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['blocks' => [['type' => 'a'], ['type' => 'b']]]);

        $result = $this->tool->reorderBlocks('uuid', 'en', 'blocks', [0]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('does not match', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ArticleBlockReorderTool::class, 'reorderBlocks');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_article_block_reorder', $attributes[0]->newInstance()->name);
    }
}
