<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;
use Sulu\Bundle\PreviewBundle\Domain\Model\PreviewLinkInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Preview\PreviewLinkGenerateTool;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PreviewLinkGenerateToolTest extends TestCase
{
    private PreviewLinkManagerInterface&MockObject $previewLinkManager;
    private RouterInterface&MockObject $router;
    private PreviewLinkGenerateTool $tool;

    protected function setUp(): void
    {
        $this->previewLinkManager = $this->createMock(PreviewLinkManagerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->tool = new PreviewLinkGenerateTool($this->previewLinkManager, $this->router);
    }

    public function testGeneratePreviewLinkForPage(): void
    {
        $previewLink = $this->createMock(PreviewLinkInterface::class);
        $previewLink->method('getToken')->willReturn('abc123');
        $previewLink->method('getResourceKey')->willReturn('pages');
        $previewLink->method('getResourceId')->willReturn('page-uuid-1');
        $previewLink->method('getLocale')->willReturn('en');

        $this->previewLinkManager
            ->expects($this->once())
            ->method('generate')
            ->with('pages', 'page-uuid-1', 'en', ['webspaceKey' => 'example'])
            ->willReturn($previewLink);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('sulu_preview.public_preview', ['token' => 'abc123'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/preview/abc123');

        $result = $this->tool->generatePreviewLink('pages', 'page-uuid-1', 'en', 'example');

        $this->assertTrue($result['success']);
        $this->assertSame('https://example.com/preview/abc123', $result['preview_url']);
        $this->assertSame('abc123', $result['token']);
        $this->assertSame('pages', $result['resourceKey']);
        $this->assertSame('page-uuid-1', $result['resourceId']);
        $this->assertSame('en', $result['locale']);
    }

    public function testGeneratePreviewLinkForArticle(): void
    {
        $previewLink = $this->createMock(PreviewLinkInterface::class);
        $previewLink->method('getToken')->willReturn('def456');
        $previewLink->method('getResourceKey')->willReturn('articles');
        $previewLink->method('getResourceId')->willReturn('article-uuid-1');
        $previewLink->method('getLocale')->willReturn('de');

        $this->previewLinkManager
            ->expects($this->once())
            ->method('generate')
            ->with('articles', 'article-uuid-1', 'de', [])
            ->willReturn($previewLink);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('sulu_preview.public_preview', ['token' => 'def456'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/preview/def456');

        $result = $this->tool->generatePreviewLink('articles', 'article-uuid-1', 'de');

        $this->assertTrue($result['success']);
        $this->assertSame('https://example.com/preview/def456', $result['preview_url']);
        $this->assertSame('def456', $result['token']);
        $this->assertSame('articles', $result['resourceKey']);
        $this->assertSame('article-uuid-1', $result['resourceId']);
        $this->assertSame('de', $result['locale']);
    }

    public function testGeneratePreviewLinkPassesWebspaceInOptions(): void
    {
        $previewLink = $this->createMock(PreviewLinkInterface::class);
        $previewLink->method('getToken')->willReturn('tok');

        $this->previewLinkManager
            ->expects($this->once())
            ->method('generate')
            ->with('pages', 'uuid-1', 'en', ['webspaceKey' => 'my-webspace'])
            ->willReturn($previewLink);

        $this->router->method('generate')->willReturn('https://example.com/preview/tok');

        $this->tool->generatePreviewLink('pages', 'uuid-1', 'en', 'my-webspace');
    }

    public function testGeneratePreviewLinkReturnsErrorOnException(): void
    {
        $this->previewLinkManager
            ->method('generate')
            ->willThrowException(new \RuntimeException('Resource not found'));

        $result = $this->tool->generatePreviewLink('pages', 'bad-uuid', 'en', 'example');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Resource not found', $result['error']);
        $this->assertArrayHasKey('hint', $result);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PreviewLinkGenerateTool::class, 'generatePreviewLink');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'generatePreviewLink() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_preview_link_generate', $instance->name);
    }
}
