<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Tool\BlockRemoveTool;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BlockRemoveTool::class)]
final class BlockRemoveToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private BlockRemoveTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new BlockRemoveTool($this->messageBus, $this->pageRepository, $this->contentManager);
    }

    public function testRemoveBlockRemovesAtIndex(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'image', 'src' => '/img.jpg'],
            ['type' => 'text', 'title' => 'Third'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);

                return $envelope->with(new HandledStamp($this->createPageMock(), 'handler'));
            });

        $result = $this->tool->removeBlock('test-uuid', 'en', 'blocks', 1);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['blockCount']);
        $this->assertSame(1, $result['removedIndex']);
    }

    public function testRemoveBlockReturnsErrorForInvalidIndex(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->tool->removeBlock('test-uuid', 'en', 'blocks', 5);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('out of range', $result['error']);
    }

    public function testRemoveBlockReturnsErrorForNegativeIndex(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->tool->removeBlock('test-uuid', 'en', 'blocks', -1);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('out of range', $result['error']);
    }

    public function testRemoveBlockReturnsSuccessWithRemovedIndex(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->removeBlock('test-uuid', 'en', 'blocks', 0);

        $this->assertArrayHasKey('removedIndex', $result);
        $this->assertSame(0, $result['removedIndex']);
        $this->assertSame(1, $result['blockCount']);
    }

    public function testRemoveBlockPreservesOtherBlocks(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'Keep First'],
            ['type' => 'image', 'src' => '/remove-me.jpg'],
            ['type' => 'text', 'title' => 'Keep Last'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->removeBlock('test-uuid', 'en', 'blocks', 1);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['blockCount']);
    }

    public function testRemoveBlockMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(BlockRemoveTool::class, 'removeBlock');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'removeBlock() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_block_remove', $instance->name);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function setupPageWithBlocks(array $blocks): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn('test-uuid');

        $this->pageRepository->method('getOneBy')->willReturn($page);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([
            'template' => 'default',
            'title' => 'Test Page',
            'blocks' => $blocks,
        ]);
    }

    private function createPageMock(): PageInterface&MockObject
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn('test-uuid');

        return $page;
    }
}
