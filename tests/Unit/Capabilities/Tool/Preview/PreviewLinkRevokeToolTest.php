<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Preview\PreviewLinkRevokeTool;

class PreviewLinkRevokeToolTest extends TestCase
{
    private PreviewLinkManagerInterface&MockObject $previewLinkManager;
    private PreviewLinkRevokeTool $tool;

    protected function setUp(): void
    {
        $this->previewLinkManager = $this->createMock(PreviewLinkManagerInterface::class);
        $this->tool = new PreviewLinkRevokeTool($this->previewLinkManager);
    }

    public function testRevokePreviewLinkSuccess(): void
    {
        $this->previewLinkManager
            ->expects($this->once())
            ->method('revoke')
            ->with('pages', 'page-uuid-1', 'en');

        $result = $this->tool->revokePreviewLink('page', 'page-uuid-1', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('revoked', $result['action']);
        $this->assertSame('pages', $result['resourceKey']);
        $this->assertSame('page-uuid-1', $result['resourceId']);
        $this->assertSame('en', $result['locale']);
    }

    public function testTypeIsMappedToResourceKeyForRevoke(): void
    {
        $capturedResourceKey = null;
        $this->previewLinkManager
            ->expects($this->once())
            ->method('revoke')
            ->willReturnCallback(function (string $resourceKey) use (&$capturedResourceKey): void {
                $capturedResourceKey = $resourceKey;
            });

        $this->tool->revokePreviewLink('article', 'article-uuid-1', 'en');

        $this->assertSame('articles', $capturedResourceKey, 'Singular "article" must be mapped to plural "articles" before calling the manager.');
    }

    public function testRevokePreviewLinkReturnsErrorOnException(): void
    {
        $this->previewLinkManager
            ->method('revoke')
            ->willThrowException(new \RuntimeException('No preview link found'));

        $result = $this->tool->revokePreviewLink('page', 'bad-uuid', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No preview link found', $result['error']);
        $this->assertArrayHasKey('hint', $result);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PreviewLinkRevokeTool::class, 'revokePreviewLink');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'revokePreviewLink() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_preview_link_revoke', $instance->name);
    }

    public function testTypeParameterHasSchemaAttributeWithSingularEnum(): void
    {
        $reflection = new \ReflectionMethod(PreviewLinkRevokeTool::class, 'revokePreviewLink');
        $parameter = $reflection->getParameters()[0];
        $this->assertSame('type', $parameter->getName());

        $attributes = $parameter->getAttributes(Schema::class);
        $this->assertCount(1, $attributes);

        $schema = $attributes[0]->newInstance();
        $this->assertSame(['page', 'article'], $schema->enum);
    }
}
