<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Tool\SnippetListTool;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

#[CoversClass(SnippetListTool::class)]
final class SnippetListToolTest extends TestCase
{
    private SnippetRepositoryInterface&MockObject $snippetRepository;
    private ContentManagerInterface&MockObject $contentManager;
    private SnippetListTool $tool;

    protected function setUp(): void
    {
        $this->snippetRepository = $this->createMock(SnippetRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->tool = new SnippetListTool($this->snippetRepository, $this->contentManager);
    }

    public function testListSnippetsReturnsPaginatedResults(): void
    {
        $snippet = $this->createMock(SnippetInterface::class);
        $snippet->method('getUuid')->willReturn('s-uuid');
        $dimensionContent = $this->createMock(DimensionContentInterface::class);

        $this->snippetRepository->method('findBy')->willReturn([$snippet]);
        $this->snippetRepository->method('countBy')->willReturn(1);
        $this->contentManager->method('resolve')->willReturn($dimensionContent);
        $this->contentManager->method('normalize')->willReturn(['title' => 'Footer']);

        $result = $this->tool->listSnippets('en');

        $this->assertArrayHasKey('snippets', $result);
        $this->assertCount(1, $result['snippets']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['limit']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(SnippetListTool::class, 'listSnippets');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_snippet_list', $attributes[0]->newInstance()->name);
    }
}
