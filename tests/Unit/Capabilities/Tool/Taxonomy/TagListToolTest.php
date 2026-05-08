<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Taxonomy\TagListTool;

#[CoversClass(TagListTool::class)]
final class TagListToolTest extends TestCase
{
    private TagRepositoryInterface&MockObject $tagRepository;
    private TagListTool $tool;

    protected function setUp(): void
    {
        $this->tagRepository = $this->createMock(TagRepositoryInterface::class);
        $this->tool = new TagListTool($this->tagRepository);
    }

    public function testListTagsReturnsAllTags(): void
    {
        $tag1 = $this->createMock(TagInterface::class);
        $tag1->method('getId')->willReturn(1);
        $tag1->method('getName')->willReturn('news');

        $tag2 = $this->createMock(TagInterface::class);
        $tag2->method('getId')->willReturn(2);
        $tag2->method('getName')->willReturn('blog');

        $this->tagRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$tag1, $tag2]);

        $result = $this->tool->listTags();

        $this->assertArrayHasKey('tags', $result);
        $this->assertCount(2, $result['tags']);
        $this->assertSame(['id' => 1, 'name' => 'news'], $result['tags'][0]);
        $this->assertSame(['id' => 2, 'name' => 'blog'], $result['tags'][1]);
    }

    public function testListTagsReturnsEmptyArrayWhenNoTags(): void
    {
        $this->tagRepository->method('findAll')->willReturn([]);

        $result = $this->tool->listTags();

        $this->assertSame(['tags' => []], $result);
    }

    public function testListTagsMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(TagListTool::class, 'listTags');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listTags() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_tag_list', $instance->name);
    }
}
