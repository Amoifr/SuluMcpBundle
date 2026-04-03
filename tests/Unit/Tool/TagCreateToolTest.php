<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\McpServerBundle\Tool\TagCreateTool;

#[CoversClass(TagCreateTool::class)]
final class TagCreateToolTest extends TestCase
{
    private TagManagerInterface&MockObject $tagManager;
    private TagCreateTool $tool;

    protected function setUp(): void
    {
        $this->tagManager = $this->createMock(TagManagerInterface::class);
        $this->tool = new TagCreateTool($this->tagManager);
    }

    public function testCreateTagReturnsSuccessWithIdAndName(): void
    {
        $mockTag = $this->createMock(TagInterface::class);
        $mockTag->method('getId')->willReturn(42);
        $mockTag->method('getName')->willReturn('breaking-news');

        $this->tagManager->expects($this->once())
            ->method('save')
            ->with(['name' => 'breaking-news'])
            ->willReturn($mockTag);

        $result = $this->tool->createTag('breaking-news');

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['id']);
        $this->assertSame('breaking-news', $result['name']);
    }

    public function testCreateTagReturnsErrorOnException(): void
    {
        $this->tagManager->method('save')
            ->willThrowException(new \RuntimeException('Duplicate tag'));

        $result = $this->tool->createTag('existing-tag');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('existing-tag', $result['error']);
        $this->assertStringContainsString('Duplicate tag', $result['error']);
        $this->assertArrayNotHasKey('success', $result);
    }

    public function testCreateTagMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(TagCreateTool::class, 'createTag');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createTag() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_tag_create', $instance->name);
    }
}
