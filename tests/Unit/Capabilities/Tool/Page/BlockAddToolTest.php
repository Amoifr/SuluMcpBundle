<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\BlockAddTool;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(BlockAddTool::class)]
final class BlockAddToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private BlockIdGeneratorInterface&MockObject $blockIdGenerator;
    private BlockAddTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->blockIdGenerator = $this->createMock(BlockIdGeneratorInterface::class);
        $this->blockIdGenerator->method('generateId')->willReturn('generated-id');
        $this->tool = new BlockAddTool(
            $this->messageBus,
            $this->pageRepository,
            $this->contentManager,
            $this->blockIdGenerator,
        );
    }

    public function testAddBlockAppendsToEnd(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);

                return $envelope->with(new HandledStamp($this->createPageMock(), 'handler'));
            });

        $result = $this->tool->addBlock('test-uuid', 'en', 'image', 'blocks', ['src' => '/img.jpg']);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['blockCount']);
        $this->assertSame(2, $result['addedAt']);
    }

    public function testAddBlockInsertsAtPosition(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
            ['type' => 'text', 'title' => 'Second'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $dispatchedBlocks = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$dispatchedBlocks) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);

                return $envelope->with(new HandledStamp($this->createPageMock(), 'handler'));
            });

        $result = $this->tool->addBlock('test-uuid', 'en', 'image', 'blocks', [], 0);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['blockCount']);
        $this->assertSame(0, $result['addedAt']);
    }

    public function testAddBlockSetsBlockType(): void
    {
        $this->setupPageWithBlocks([]);

        $dispatchedData = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->addBlock('test-uuid', 'en', 'hero_block', 'blocks');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['blockCount']);
    }

    public function testAddBlockMergesBlockData(): void
    {
        $this->setupPageWithBlocks([]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->addBlock('test-uuid', 'en', 'text', 'blocks', ['title' => 'Hello', 'description' => 'World']);

        $this->assertTrue($result['success']);
    }

    public function testAddBlockPreservesLocaleInModifyMessage(): void
    {
        $this->setupPageWithBlocks([]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->addBlock('test-uuid', 'de', 'text', 'blocks');

        $this->assertTrue($result['success']);
    }

    public function testAddBlockReadsCurrentPageBeforeModifying(): void
    {
        $this->setupPageWithBlocks([]);

        $this->pageRepository->expects($this->once())
            ->method('getOneBy');

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $this->tool->addBlock('test-uuid', 'en', 'text', 'blocks');
    }

    public function testAddBlockReturnsSuccessWithBlockCount(): void
    {
        $existingBlocks = [
            ['type' => 'text', 'title' => 'First'],
        ];
        $this->setupPageWithBlocks($existingBlocks);

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($this->createPageMock(), 'handler')));

        $result = $this->tool->addBlock('test-uuid', 'en', 'text', 'blocks');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('blockCount', $result);
        $this->assertArrayHasKey('addedAt', $result);
        $this->assertArrayHasKey('uuid', $result);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['blockCount']);
    }

    public function testAddBlockMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(BlockAddTool::class, 'addBlock');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'addBlock() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_block_add', $instance->name);
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
