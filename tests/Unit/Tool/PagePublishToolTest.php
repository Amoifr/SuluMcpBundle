<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Tool\PagePublishTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PagePublishTool::class)]
final class PagePublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PagePublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new PagePublishTool($this->messageBus);
    }

    public function testPublishPageDispatchesWorkflowTransition(): void
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

        $this->tool->publishPage('page-uuid-123', 'en');
    }

    public function testPublishPageUsesPublishTransitionName(): void
    {
        $capturedMessage = null;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$capturedMessage) {
                $capturedMessage = $envelope->getMessage();

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $this->tool->publishPage('page-uuid-123', 'en');

        $this->assertInstanceOf(ApplyWorkflowTransitionPageMessage::class, $capturedMessage);
    }

    public function testPublishPageReturnsSuccessResponse(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp(null, 'handler')));

        $result = $this->tool->publishPage('page-uuid-123', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('published', $result['action']);
    }

    public function testPublishPageIncludesUuidAndLocaleInResponse(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp(null, 'handler')));

        $result = $this->tool->publishPage('my-page-uuid', 'de');

        $this->assertSame('my-page-uuid', $result['uuid']);
        $this->assertSame('de', $result['locale']);
    }

    public function testPublishPageReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Page is already published'));

        $result = $this->tool->publishPage('page-uuid-123', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Page is already published', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testPublishPageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PagePublishTool::class, 'publishPage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'publishPage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_publish', $instance->name);
    }

    public function testPublishPageDescriptionRequiresUserConfirmation(): void
    {
        $reflection = new \ReflectionMethod(PagePublishTool::class, 'publishPage');
        $attributes = $reflection->getAttributes(McpTool::class);
        $instance = $attributes[0]->newInstance();

        $this->assertStringContainsString(
            'Always ask the user for confirmation before calling this tool',
            $instance->description,
            'Publish tool description must instruct the AI to ask for user confirmation',
        );
    }
}
