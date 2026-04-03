<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Tool\BlockUpdateTool;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BlockUpdateTool::class)]
final class BlockUpdateToolTest extends TestCase
{
    private PageRepositoryInterface&MockObject $pageRepository;
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private MessageBusInterface&MockObject $messageBus;
    private BlockUpdateTool $tool;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new BlockUpdateTool(
            $this->messageBus,
            $this->pageRepository,
            $this->articleRepository,
            $this->contentManager,
        );
    }

    public function testUpdatePageBlockById(): void
    {
        $page = $this->createMock(PageInterface::class);
        $this->pageRepository->method('getOneBy')->willReturn($page);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'default',
            'title' => 'Test Page',
            'blocks' => [
                ['_id' => 'block-1', 'type' => 'text', 'title' => 'Old Title', 'description' => '<p>Old</p>'],
                ['_id' => 'block-2', 'type' => 'image', 'title' => 'Image', 'src' => '/img.jpg'],
            ],
        ]);

        $updatedPage = $this->createMock(PageInterface::class);
        $updatedPage->method('getUuid')->willReturn('page-uuid');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $envelope): bool {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);

                return true;
            }))
            ->willReturn(new Envelope($updatedPage, [new HandledStamp($updatedPage, 'handler')]));

        $result = $this->tool->updateBlock('page', 'page-uuid', 'en', 'block-1', [
            'title' => 'New Title',
            'description' => '<p>New</p>',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('page-uuid', $result['uuid']);
        $this->assertSame('block-1', $result['blockId']);
        $this->assertSame('blocks', $result['blockProperty']);
        $this->assertSame(0, $result['blockIndex']);
    }

    public function testUpdateArticleBlockById(): void
    {
        $article = $this->createMock(ArticleInterface::class);
        $this->articleRepository->method('getOneBy')->willReturn($article);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'blog',
            'title' => 'Test Article',
            'content' => [
                ['_id' => 'art-block-1', 'type' => 'text', 'body' => '<p>Hello</p>'],
            ],
        ]);

        $updatedArticle = $this->createMock(ArticleInterface::class);
        $updatedArticle->method('getUuid')->willReturn('article-uuid');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $envelope): bool {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyArticleMessage::class, $message);

                return true;
            }))
            ->willReturn(new Envelope($updatedArticle, [new HandledStamp($updatedArticle, 'handler')]));

        $result = $this->tool->updateBlock('article', 'article-uuid', 'en', 'art-block-1', [
            'body' => '<p>Updated</p>',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('article-uuid', $result['uuid']);
        $this->assertSame('art-block-1', $result['blockId']);
        $this->assertSame('content', $result['blockProperty']);
    }

    public function testBlockNotFoundReturnsError(): void
    {
        $page = $this->createMock(PageInterface::class);
        $this->pageRepository->method('getOneBy')->willReturn($page);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'default',
            'title' => 'Test',
            'blocks' => [
                ['_id' => 'block-1', 'type' => 'text'],
            ],
        ]);

        $result = $this->tool->updateBlock('page', 'page-uuid', 'en', 'nonexistent', ['title' => 'New']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('nonexistent', $result['error']);
        $this->assertArrayHasKey('hint', $result);
    }

    public function testEntityNotFoundReturnsError(): void
    {
        $this->pageRepository->method('getOneBy')
            ->willThrowException(new \RuntimeException('Not found'));

        $result = $this->tool->updateBlock('page', 'missing-uuid', 'en', 'block-1', ['title' => 'New']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('missing-uuid', $result['error']);
    }

    public function testInvalidTypeReturnsError(): void
    {
        $result = $this->tool->updateBlock('invalid', 'uuid', 'en', 'block-1', ['title' => 'New']);

        $this->assertArrayHasKey('error', $result);
    }

    public function testPartialMergePreservesExistingFields(): void
    {
        $page = $this->createMock(PageInterface::class);
        $this->pageRepository->method('getOneBy')->willReturn($page);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'default',
            'title' => 'Test Page',
            'blocks' => [
                ['_id' => 'block-1', 'type' => 'text', 'title' => 'Keep This', 'description' => '<p>Old</p>', 'settings' => ['color' => 'red']],
            ],
        ]);

        $updatedPage = $this->createMock(PageInterface::class);
        $updatedPage->method('getUuid')->willReturn('page-uuid');

        $dispatchedBlocks = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (Envelope $envelope) use (&$dispatchedBlocks): bool {
                /** @var ModifyPageMessage $message */
                $message = $envelope->getMessage();
                $data = (new \ReflectionProperty($message, 'data'))->getValue($message);
                $dispatchedBlocks = $data['blocks'];

                return true;
            }))
            ->willReturn(new Envelope($updatedPage, [new HandledStamp($updatedPage, 'handler')]));

        $this->tool->updateBlock('page', 'page-uuid', 'en', 'block-1', [
            'description' => '<p>New</p>',
        ]);

        $this->assertNotNull($dispatchedBlocks);
        $this->assertSame('Keep This', $dispatchedBlocks[0]['title']);
        $this->assertSame('<p>New</p>', $dispatchedBlocks[0]['description']);
        $this->assertSame(['color' => 'red'], $dispatchedBlocks[0]['settings']);
        $this->assertSame('text', $dispatchedBlocks[0]['type']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(BlockUpdateTool::class, 'updateBlock');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_block_update', $instance->name);
    }
}
