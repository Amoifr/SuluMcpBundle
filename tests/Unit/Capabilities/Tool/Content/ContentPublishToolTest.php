<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Content;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Content\ContentPublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ContentPublishTool::class)]
final class ContentPublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentPublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $resolver = new ContentTypeResolver(
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(ArticleRepositoryInterface::class),
            $this->createMock(SnippetRepositoryInterface::class),
        );
        $this->tool = new ContentPublishTool($this->messageBus, $resolver);
    }

    public function testPublishPageDispatchesTransitionWithPublishName(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $message = $envelope->getMessage();
                $this->assertInstanceOf(ApplyWorkflowTransitionPageMessage::class, $message);
                $this->assertArrayHasKey(EnableFlushStamp::class, $envelope->all());

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->publishContent('page', 'uuid-1', 'en');

        $this->assertSame(['success' => true, 'type' => 'page', 'uuid' => 'uuid-1', 'action' => 'published', 'locale' => 'en'], $result);
    }

    public function testUnsupportedTypeReturnsError(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->assertArrayHasKey('error', $this->tool->publishContent('media', 'uuid-1', 'en'));
    }

    public function testErrorOnException(): void
    {
        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('boom'));
        $this->assertStringContainsString('boom', $this->tool->publishContent('article', 'uuid-1', 'en')['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $attributes = (new \ReflectionMethod(ContentPublishTool::class, 'publishContent'))->getAttributes(McpTool::class);
        $this->assertSame('sulu_content_publish', $attributes[0]->newInstance()->name);
    }
}
