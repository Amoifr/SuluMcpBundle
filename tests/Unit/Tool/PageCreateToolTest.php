<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Tool\PageCreateTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PageCreateTool::class)]
final class PageCreateToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentManagerInterface&MockObject $contentManager;
    private PageCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new PageCreateTool($this->messageBus, $this->contentManager);
    }

    public function testCreatePageDispatchesCreatePageMessage(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('page-uuid-123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test Page']);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test Page', 'parent-uuid');

        $this->assertTrue($result['success']);
        $this->assertSame('page-uuid-123', $result['uuid']);
    }

    public function testCreatePageIncludesLocaleInData(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage) {
                /** @var CreatePageMessage $message */
                $message = $envelope->getMessage();
                $this->assertInstanceOf(CreatePageMessage::class, $message);

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');
    }

    public function testCreatePageGeneratesUrlFromTitleWhenUrlIsNull(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $capturedMessage = null;
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($mockPage, &$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp($mockPage, 'handler'));
            });

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->createPage('example', 'en', 'default', 'My Test Page', 'parent-uuid');

        $this->assertInstanceOf(CreatePageMessage::class, $capturedMessage);
    }

    public function testCreatePageMergesContentIntoData(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $result = $this->tool->createPage(
            'example',
            'en',
            'default',
            'Test',
            'parent-uuid',
            null,
            ['excerpt' => 'Test excerpt'],
        );

        $this->assertTrue($result['success']);
    }

    public function testCreatePageResolvesAndNormalizesResult(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('uuid-1');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->expects($this->once())
            ->method('resolve')
            ->with($mockPage, [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ])
            ->willReturn($mockDimensionContent);

        $this->contentManager->expects($this->once())
            ->method('normalize')
            ->with($mockDimensionContent)
            ->willReturn(['title' => 'Resolved Title']);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertSame(['title' => 'Resolved Title'], $result['data']);
    }

    public function testCreatePageReturnsSuccessWithUuid(): void
    {
        $mockPage = $this->createMock(PageInterface::class);
        $mockPage->method('getUuid')->willReturn('new-page-uuid');

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp($mockPage, 'handler')));

        $mockDimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($mockDimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertTrue($result['success']);
        $this->assertSame('new-page-uuid', $result['uuid']);
        $this->assertArrayHasKey('data', $result);
    }

    public function testCreatePageReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Page creation failed'));

        $result = $this->tool->createPage('example', 'en', 'default', 'Test', 'parent-uuid');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Page creation failed', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreatePageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageCreateTool::class, 'createPage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createPage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_create', $instance->name);
    }
}
