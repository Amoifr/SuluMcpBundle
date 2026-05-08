<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Doctrine\Common\Collections\ArrayCollection;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageTreeTool;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Route\Domain\Model\Route;

class PageTreeToolTest extends TestCase
{
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private PageTreeTool $tool;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new PageTreeTool($this->pageRepository, $this->contentManager);
    }

    public function testGetPageTreeReturnsTreeStructure(): void
    {
        $page = $this->createPageMock('uuid-1', 'Homepage', '/');

        $this->pageRepository->method('findByAsTree')->willReturn([$page]);
        $this->setupContentManagerForPage($page, 'Homepage', '/', 'homepage', 'published');

        $result = $this->tool->getPageTree('example', 'en');

        $this->assertSame('example', $result['webspace']);
        $this->assertSame('en', $result['locale']);
        $this->assertArrayHasKey('tree', $result);
        $this->assertCount(1, $result['tree']);
    }

    public function testGetPageTreeBuildsNodesWithRequiredFields(): void
    {
        $page = $this->createPageMock('uuid-1', 'Homepage', '/');

        $this->pageRepository->method('findByAsTree')->willReturn([$page]);
        $this->setupContentManagerForPage($page, 'Homepage', '/', 'homepage', 'published');

        $result = $this->tool->getPageTree('example', 'en');
        $node = $result['tree'][0];

        $this->assertSame('uuid-1', $node['uuid']);
        $this->assertSame('Homepage', $node['title']);
        $this->assertSame('/', $node['url']);
        $this->assertSame('homepage', $node['templateKey']);
        $this->assertFalse($node['hasChildren']);
        $this->assertNull($node['parentUuid']);
        $this->assertSame(0, $node['depth']);
        $this->assertSame('published', $node['workflowPlace']);
        $this->assertArrayHasKey('availableLocales', $node);
        $this->assertArrayHasKey('children', $node);
    }

    public function testGetPageTreeHandlesNestedChildren(): void
    {
        $parent = $this->createMock(PageInterface::class);
        $parent->method('getUuid')->willReturn('uuid-parent');
        $parent->method('getParent')->willReturn(null);

        $child = $this->createMock(PageInterface::class);
        $child->method('getUuid')->willReturn('uuid-child');
        $child->method('getChildren')->willReturn(new ArrayCollection([]));
        $child->method('getParent')->willReturn($parent);

        $parent->method('getChildren')->willReturn(new ArrayCollection([$child]));

        $this->pageRepository->method('findByAsTree')->willReturn([$parent]);

        $parentDimensionContent = $this->createDimensionContentMock('Homepage', '/', 'homepage', 'published');
        $childDimensionContent = $this->createDimensionContentMock('About Us', '/about', 'default', 'draft');

        $this->contentManager->method('resolve')
            ->willReturnCallback(function (PageInterface $page) use ($parent, $parentDimensionContent, $childDimensionContent) {
                if ($page === $parent) {
                    return $parentDimensionContent;
                }

                return $childDimensionContent;
            });

        $result = $this->tool->getPageTree('example', 'en');

        $this->assertCount(1, $result['tree']);
        $parentNode = $result['tree'][0];
        $this->assertTrue($parentNode['hasChildren']);
        $this->assertCount(1, $parentNode['children']);

        $childNode = $parentNode['children'][0];
        $this->assertSame('uuid-child', $childNode['uuid']);
        $this->assertSame('About Us', $childNode['title']);
        $this->assertSame(1, $childNode['depth']);
        $this->assertSame('uuid-parent', $childNode['parentUuid']);
    }

    public function testGetPageTreeReturnsEmptyTreeForEmptyWebspace(): void
    {
        $this->pageRepository->method('findByAsTree')->willReturn([]);

        $result = $this->tool->getPageTree('example', 'en');

        $this->assertSame([], $result['tree']);
    }

    public function testGetPageTreeMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageTreeTool::class, 'getPageTree');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'getPageTree() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_tree', $instance->name);
    }

    /**
     * @param PageInterface[] $children
     */
    private function createPageMock(string $uuid, string $title, string $url, array $children = []): PageInterface&MockObject
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn($uuid);
        $page->method('getChildren')->willReturn(new ArrayCollection($children));
        $page->method('getParent')->willReturn(null);

        return $page;
    }

    private function createDimensionContentMock(
        string $title,
        string $slug,
        string $templateKey,
        string $workflowPlace,
    ): PageDimensionContentInterface&MockObject {
        $dimensionContent = $this->createMock(PageDimensionContentInterface::class);
        $dimensionContent->method('getTitle')->willReturn($title);
        $dimensionContent->method('getTemplateKey')->willReturn($templateKey);
        $dimensionContent->method('getWorkflowPlace')->willReturn($workflowPlace);
        $dimensionContent->method('getAvailableLocales')->willReturn(['en']);

        $route = $this->createMock(Route::class);
        $route->method('getSlug')->willReturn($slug);
        $dimensionContent->method('getRoute')->willReturn($route);

        return $dimensionContent;
    }

    private function setupContentManagerForPage(
        PageInterface $page,
        string $title,
        string $slug,
        string $templateKey,
        string $workflowPlace,
    ): void {
        $dimensionContent = $this->createDimensionContentMock($title, $slug, $templateKey, $workflowPlace);
        $this->contentManager->method('resolve')->with($page, $this->anything())->willReturn($dimensionContent);
    }
}
