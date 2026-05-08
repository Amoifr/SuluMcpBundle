<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\BlockReorderTool;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BlockReorderTool::class)]
final class BlockReorderToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private BlockReorderTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new BlockReorderTool($this->messageBus, $this->pageRepository, $this->contentManager);
    }

    public function testReorderBlocksChangesOrder(): void
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

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [2, 0, 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['blockCount']);
        $this->assertSame([2, 0, 1], $result['order']);
    }

    public function testReorderBlocksReturnsErrorForWrongLength(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
            ['type' => 'text', 'title' => 'Third'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [0, 1]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('does not match block count', $result['error']);
    }

    public function testReorderBlocksReturnsErrorForDuplicateIndices(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
            ['type' => 'text', 'title' => 'Third'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [0, 0, 1]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('exactly once', $result['error']);
    }

    public function testReorderBlocksReturnsErrorForOutOfRangeIndex(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
            ['type' => 'text', 'title' => 'Third'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [0, 1, 5]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('exactly once', $result['error']);
    }

    public function testReorderBlocksPreservesBlockContent(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'Alpha'],
            ['type' => 'image', 'src' => '/beta.jpg'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [1, 0]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['blockCount']);
    }

    public function testReorderBlocksReturnsSuccessWithOrder(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->reorderBlocks('test-uuid', 'en', 'blocks', [1, 0]);

        $this->assertArrayHasKey('order', $result);
        $this->assertSame([1, 0], $result['order']);
    }

    public function testReorderBlocksMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(BlockReorderTool::class, 'reorderBlocks');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'reorderBlocks() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_block_reorder', $instance->name);
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
