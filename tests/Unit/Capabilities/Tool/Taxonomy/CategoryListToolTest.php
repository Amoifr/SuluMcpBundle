<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\CategoryBundle\Api\Category as ApiCategory;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Taxonomy\CategoryListTool;

#[CoversClass(CategoryListTool::class)]
final class CategoryListToolTest extends TestCase
{
    private CategoryManagerInterface&MockObject $categoryManager;
    private CategoryListTool $tool;

    protected function setUp(): void
    {
        $this->categoryManager = $this->createMock(CategoryManagerInterface::class);
        $this->tool = new CategoryListTool($this->categoryManager);
    }

    public function testListCategoriesReturnsTree(): void
    {
        $child = $this->createMock(ApiCategory::class);
        $child->method('getId')->willReturn(2);
        $child->method('getName')->willReturn('PHP');
        $child->method('getKey')->willReturn('php');
        $child->method('getChildren')->willReturn([]);

        $parent = $this->createMock(ApiCategory::class);
        $parent->method('getId')->willReturn(1);
        $parent->method('getName')->willReturn('Technology');
        $parent->method('getKey')->willReturn('technology');
        $parent->method('getChildren')->willReturn([$child]);

        $this->categoryManager->method('findChildrenByParentId')
            ->with(null)
            ->willReturn([$parent]);

        $this->categoryManager->method('getApiObjects')
            ->willReturn([$parent]);

        $result = $this->tool->listCategories('en');

        $this->assertArrayHasKey('categories', $result);
        $this->assertCount(1, $result['categories']);
        $this->assertSame('Technology', $result['categories'][0]['name']);
        $this->assertCount(1, $result['categories'][0]['children']);
        $this->assertSame('PHP', $result['categories'][0]['children'][0]['name']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(CategoryListTool::class, 'listCategories');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_category_list', $attributes[0]->newInstance()->name);
    }
}
