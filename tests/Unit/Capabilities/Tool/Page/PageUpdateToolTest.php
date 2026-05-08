<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageUpdateTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PageUpdateTool::class)]
final class PageUpdateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private PageRepositoryInterface&MockObject $pageRepository;
    private PageUpdateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->tool = new PageUpdateTool($this->messageBus, $this->contentManager, $this->pageRepository);
    }

    private function setUpReadModifyWrite(string $uuid, string $locale, array $currentData = []): PageInterface&MockObject
    {
        $existingPage = $this->createMock(PageInterface::class);
        $existingPage->method('getUuid')->willReturn($uuid);

        $this->pageRepository->method('getOneBy')
            ->with(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
            )
            ->willReturn($existingPage);

        $currentDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')
            ->willReturn($currentDimensionContent);
        $this->contentManager->method('normalize')
            ->willReturn($currentData);

        return $existingPage;
    }

    public function testUpdatePageReadsCurrentStateBeforeModifying(): void
    {
        $this->setUpReadModifyWrite('uuid-1', 'en', ['template' => 'default', 'title' => 'Old Title']);

        $mockUpdatedPage = $this->createMock(PageInterface::class);
        $mockUpdatedPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockUpdatedPage) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($mockUpdatedPage, 'handler'));
            });

        $result = $this->tool->updatePage('uuid-1', 'en', 'New Title');

        $this->assertTrue($result['success']);
    }

    public function testUpdatePageIncludesTemplateFromCurrentState(): void
    {
        $this->setUpReadModifyWrite('uuid-1', 'en', [
            'template' => 'default',
            'title' => 'Existing',
            'article' => '<p>Existing content</p>',
        ]);

        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $capturedData = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage, &$capturedData) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ModifyPageMessage::class, $message);
                $capturedData = (new \ReflectionProperty($message, 'data'))->getValue($message);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        // Update only content, no template provided — should use current template
        $this->tool->updatePage('uuid-1', 'en', null, null, null, ['article' => '<p>Updated</p>']);

        $this->assertSame('default', $capturedData['template']);
        $this->assertSame('<p>Updated</p>', $capturedData['article']);
    }

    public function testUpdatePageMergesContentWithExistingData(): void
    {
        $this->setUpReadModifyWrite('uuid-1', 'en', [
            'template' => 'default',
            'title' => 'Old Title',
            'article' => '<p>Old content</p>',
        ]);

        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $result = $this->tool->updatePage(
            'uuid-1',
            'en',
            null,
            null,
            null,
            ['article' => '<p>New content</p>'],
        );

        $this->assertTrue($result['success']);
    }

    public function testUpdatePageReturnsSuccessWithUuid(): void
    {
        $this->setUpReadModifyWrite('uuid-1', 'en', ['template' => 'default', 'title' => 'Title']);

        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $result = $this->tool->updatePage('uuid-1', 'en', 'Updated Title');

        $this->assertTrue($result['success']);
        $this->assertSame('uuid-1', $result['uuid']);
    }

    public function testUpdatePageReturnsErrorOnException(): void
    {
        $this->pageRepository->method('getOneBy')
            ->willThrowException(new \RuntimeException('Page not found'));

        $result = $this->tool->updatePage('non-existent', 'en', 'Title');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Page not found', $result['error']);
    }

    public function testUpdatePageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageUpdateTool::class, 'updatePage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'updatePage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_update', $instance->name);
    }

    public function testNormalizeContentPassesThroughFlatMap(): void
    {
        $input = ['article' => '<p>Hello</p>', 'title' => 'Test'];
        $this->assertSame($input, PageUpdateTool::normalizeContent($input));
    }

    public function testNormalizeContentFlattensListOfObjects(): void
    {
        // AI sends: [{"article": "<p>Hello</p>"}]
        $input = [['article' => '<p>Hello</p>']];
        $this->assertSame(['article' => '<p>Hello</p>'], PageUpdateTool::normalizeContent($input));
    }

    public function testNormalizeContentHandlesNameValueFormat(): void
    {
        // AI sends: [{"name": "article", "value": "<p>Hello</p>"}]
        $input = [['name' => 'article', 'value' => '<p>Hello</p>']];
        $this->assertSame(['article' => '<p>Hello</p>'], PageUpdateTool::normalizeContent($input));
    }

    public function testNormalizeContentMergesMultipleListItems(): void
    {
        $input = [
            ['article' => '<p>Content</p>'],
            ['subtitle' => 'Sub'],
        ];
        $this->assertSame(
            ['article' => '<p>Content</p>', 'subtitle' => 'Sub'],
            PageUpdateTool::normalizeContent($input),
        );
    }

    public function testNormalizeContentHandlesEmptyArray(): void
    {
        $this->assertSame([], PageUpdateTool::normalizeContent([]));
    }
}
