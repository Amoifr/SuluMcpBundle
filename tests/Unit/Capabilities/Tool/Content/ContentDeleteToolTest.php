<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Content;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Message\RemoveArticleMessage;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Content\ContentDeleteTool;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\RemovePageMessage;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Application\Message\RemoveSnippetMessage;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ContentDeleteTool::class)]
final class ContentDeleteToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentTypeResolver $resolver;
    private ContentDeleteTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->resolver = new ContentTypeResolver(
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(ArticleRepositoryInterface::class),
            $this->createMock(SnippetRepositoryInterface::class),
        );
        $this->tool = new ContentDeleteTool($this->messageBus, $this->resolver);
    }

    public function testDeletePageDispatchesRemovePageMessageWithFlushStamp(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $this->assertInstanceOf(RemovePageMessage::class, $envelope->getMessage());
                $this->assertArrayHasKey(EnableFlushStamp::class, $envelope->all());

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deleteContent('page', 'uuid-1', 'en', true);

        $this->assertSame(['success' => true, 'type' => 'page', 'uuid' => 'uuid-1', 'deleted' => true], $result);
    }

    public function testDeleteSnippetDispatchesRemoveSnippetMessage(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $this->assertInstanceOf(RemoveSnippetMessage::class, $envelope->getMessage());

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deleteContent('snippet', 'uuid-2', 'en');

        $this->assertTrue($result['deleted']);
    }

    public function testDeleteArticleDispatchesRemoveArticleMessage(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $this->assertInstanceOf(RemoveArticleMessage::class, $envelope->getMessage());

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->deleteContent('article', 'uuid-3', 'en');

        $this->assertTrue($result['deleted']);
    }

    public function testUnsupportedTypeReturnsErrorWithoutDispatch(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->tool->deleteContent('contact', 'uuid-1', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testErrorOnException(): void
    {
        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $result = $this->tool->deleteContent('article', 'uuid-1', 'en');

        $this->assertStringContainsString('boom', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $attributes = (new \ReflectionMethod(ContentDeleteTool::class, 'deleteContent'))->getAttributes(McpTool::class);
        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_content_delete', $attributes[0]->newInstance()->name);
    }
}
