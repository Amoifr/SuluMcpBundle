<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Content;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Content\ContentUnpublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ContentUnpublishTool::class)]
final class ContentUnpublishToolTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private ContentUnpublishTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $resolver = new ContentTypeResolver(
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(ArticleRepositoryInterface::class),
            $this->createMock(SnippetRepositoryInterface::class),
        );
        $this->tool = new ContentUnpublishTool($this->messageBus, $resolver);
    }

    public function testUnpublishSnippetDispatchesTransition(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) {
                $this->assertInstanceOf(ApplyWorkflowTransitionSnippetMessage::class, $envelope->getMessage());
                $this->assertArrayHasKey(EnableFlushStamp::class, $envelope->all());

                return $envelope->with(new HandledStamp(null, 'handler'));
            });

        $result = $this->tool->unpublishContent('snippet', 'uuid-1', 'en');

        $this->assertSame('unpublished', $result['action']);
    }

    public function testUnsupportedTypeReturnsError(): void
    {
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->assertArrayHasKey('error', $this->tool->unpublishContent('media', 'uuid-1', 'en'));
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $attributes = (new \ReflectionMethod(ContentUnpublishTool::class, 'unpublishContent'))->getAttributes(McpTool::class);
        $this->assertSame('sulu_content_unpublish', $attributes[0]->newInstance()->name);
    }
}
