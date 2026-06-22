<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageListTool;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageListToolTest extends TestCase
{
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private PageListTool $tool;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new PageListTool($this->pageRepository, $this->contentManager);
    }

    public function testListPagesReturnsPaginatedResults(): void
    {
        $page1 = $this->createMock(PageInterface::class);
        $page1->method('getUuid')->willReturn('uuid-1');
        $page2 = $this->createMock(PageInterface::class);
        $page2->method('getUuid')->willReturn('uuid-2');

        $this->pageRepository->method('findBy')->willReturn([$page1, $page2]);
        $this->pageRepository->method('countBy')->willReturn(5);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Test']);

        $result = $this->tool->listPages('example', 'en');

        $this->assertCount(2, $result['pages']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['limit']);
        $this->assertSame('uuid-1', $result['pages'][0]['uuid']);
        $this->assertSame('uuid-2', $result['pages'][1]['uuid']);
    }

    public function testListPagesAppliesTemplateFilter(): void
    {
        $this->pageRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (array $filters): bool => isset($filters['templateKeys'])
                    && $filters['templateKeys'] === ['default']),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([]);
        $this->pageRepository->method('countBy')->willReturn(0);

        $this->tool->listPages('example', 'en', 'default');
    }

    public function testListPagesAppliesParentIdFilter(): void
    {
        $this->pageRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (array $filters): bool => isset($filters['parentId'])
                    && 'parent-uuid' === $filters['parentId']),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([]);
        $this->pageRepository->method('countBy')->willReturn(0);

        $this->tool->listPages('example', 'en', null, 'parent-uuid');
    }

    public function testListPagesDefaultsPaginationToPage1Limit20(): void
    {
        $this->pageRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (array $filters): bool => 1 === $filters['page'] && 20 === $filters['limit']),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([]);
        $this->pageRepository->method('countBy')->willReturn(0);

        $this->tool->listPages('example', 'en');
    }

    public function testListPagesResolvesAndNormalizesEachPage(): void
    {
        $page1 = $this->createMock(PageInterface::class);
        $page1->method('getUuid')->willReturn('uuid-1');
        $page2 = $this->createMock(PageInterface::class);
        $page2->method('getUuid')->willReturn('uuid-2');
        $page3 = $this->createMock(PageInterface::class);
        $page3->method('getUuid')->willReturn('uuid-3');

        $this->pageRepository->method('findBy')->willReturn([$page1, $page2, $page3]);
        $this->pageRepository->method('countBy')->willReturn(3);

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $this->contentManager
            ->expects($this->exactly(3))
            ->method('resolve')
            ->willReturn($dimensionContent);
        $this->contentManager
            ->expects($this->exactly(3))
            ->method('normalize')
            ->willReturn(['title' => 'Test']);

        $this->tool->listPages('example', 'en');
    }

    public function testListPagesMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageListTool::class, 'listPages');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listPages() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_list', $instance->name);
    }

    public function testParentIdParameterHasSchemaAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageListTool::class, 'listPages');
        $parameter = $reflection->getParameters()[3];
        $this->assertSame('parentId', $parameter->getName());

        $attributes = $parameter->getAttributes(Schema::class);
        $this->assertCount(1, $attributes);

        $schema = $attributes[0]->newInstance();
        $this->assertStringContainsString('UUID', $schema->description);
    }
}
