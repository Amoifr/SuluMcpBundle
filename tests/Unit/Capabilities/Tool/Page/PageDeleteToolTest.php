<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageDeleteTool;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\RemovePageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(PageDeleteTool::class)]
final class PageDeleteToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private PageDeleteTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->tool = new PageDeleteTool($this->messageBus);
    }

    public function testDeletePageDispatchesRemovePageMessage(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(RemovePageMessage::class, $message);

                $stamps = $envelope->all();
                $this->assertArrayHasKey(EnableFlushStamp::class, $stamps);

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deletePage('page-uuid-123', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('page-uuid-123', $result['uuid']);
        $this->assertTrue($result['deleted']);
    }

    public function testDeletePageDefaultsForceRemoveChildrenToFalse(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(RemovePageMessage::class, $message);

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $this->tool->deletePage('uuid-1', 'en');
    }

    public function testDeletePagePassesForceRemoveChildrenWhenTrue(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(RemovePageMessage::class, $message);

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deletePage('uuid-1', 'en', true);

        $this->assertTrue($result['success']);
    }

    public function testDeletePageReturnsSuccessResponse(): void
    {
        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (Envelope $envelope) => $envelope->with(new HandledStamp(null, 'handler')));

        $result = $this->tool->deletePage('test-uuid', 'de');

        $this->assertSame([
            'success' => true,
            'uuid' => 'test-uuid',
            'deleted' => true,
        ], $result);
    }

    public function testDeletePageReturnsErrorOnException(): void
    {
        $this->messageBus->method('dispatch')
            ->willThrowException(new \RuntimeException('Cannot delete page with children'));

        $result = $this->tool->deletePage('uuid-with-children', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Cannot delete page with children', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testDeletePageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageDeleteTool::class, 'deletePage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'deletePage() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_delete', $instance->name);
    }
}
