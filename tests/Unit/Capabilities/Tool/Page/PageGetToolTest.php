<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageGetTool;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageGetToolTest extends TestCase
{
    private PageRepositoryInterface&MockObject $pageRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private PageGetTool $tool;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new PageGetTool($this->pageRepository, $this->contentManager);
    }

    public function testGetPageReturnsNormalizedContent(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn('test-uuid-123');

        $dimensionContent = $this->createMock(DimensionContentInterface::class);
        $normalizedData = ['title' => 'Test Page', 'template' => 'default'];

        $this->pageRepository->method('getOneBy')->willReturn($page);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn($normalizedData);

        $result = $this->tool->getPage('example', 'en', 'test-uuid-123');

        $this->assertSame('test-uuid-123', $result['uuid']);
        $this->assertSame('example', $result['webspace']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame($normalizedData, $result['data']);
    }

    public function testGetPagePassesCorrectFiltersToRepository(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn('my-uuid');
        $dimensionContent = $this->createMock(DimensionContentInterface::class);

        $this->pageRepository
            ->expects($this->once())
            ->method('getOneBy')
            ->with(
                [
                    'uuid' => 'my-uuid',
                    'locale' => 'de',
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            )
            ->willReturn($page);

        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn([]);

        $this->tool->getPage('example', 'de', 'my-uuid');
    }

    public function testGetPageUsesContentManagerToResolveAndNormalize(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getUuid')->willReturn('uuid-1');
        $dimensionContent = $this->createMock(DimensionContentInterface::class);

        $this->pageRepository->method('getOneBy')->willReturn($page);

        $this->contentManager
            ->expects($this->once())
            ->method('resolve')
            ->with($page, [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ])
            ->willReturn($dimensionContent);

        $this->contentManager
            ->expects($this->once())
            ->method('normalize')
            ->with($dimensionContent)
            ->willReturn(['title' => 'Test']);

        $this->tool->getPage('example', 'en', 'uuid-1');
    }

    public function testGetPageReturnsErrorForMissingPage(): void
    {
        $this->pageRepository
            ->method('getOneBy')
            ->willThrowException(new PageNotFoundException(['uuid' => 'missing-uuid']));

        $result = $this->tool->getPage('example', 'en', 'missing-uuid');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('missing-uuid', $result['error']);
    }

    public function testGetPageMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PageGetTool::class, 'getPage');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'getPage() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_page_get', $instance->name);
    }
}
