<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Tool\PageUnpublishTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PageUnpublishTool::class)]
final class PageUnpublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PageUnpublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new PageUnpublishTool($this->messageBus);
    }

    public function testUnpublishPageDispatchesWorkflowTransition(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ApplyWorkflowTransitionPageMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $this->tool->unpublishPage('page-uuid-123', 'en');
    }

    public function testUnpublishPageUsesUnpublishTransitionName(): void
    {
        $capturedMessage = null;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $this->tool->unpublishPage('page-uuid-123', 'en');

        $this->assertInstanceOf(ApplyWorkflowTransitionPageMessage::class, $capturedMessage);
    }

    public function testUnpublishPageReturnsSuccessResponse(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp(null, 'handler')));

        $result = $this->tool->unpublishPage('page-uuid-123', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('unpublished', $result['action']);
    }

    public function testUnpublishPageReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Page is not published'));

        $result = $this->tool->unpublishPage('page-uuid-123', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Page is not published', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testUnpublishPageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageUnpublishTool::class, 'unpublishPage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'unpublishPage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_unpublish', $instance->name);
    }
}
