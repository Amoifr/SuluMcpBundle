<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Tool\NavigationGetTool;
use Sulu\Page\Domain\Repository\NavigationRepositoryInterface;

#[CoversClass(NavigationGetTool::class)]
final class NavigationGetToolTest extends TestCase
{
    private NavigationRepositoryInterface&MockObject $navigationRepository;
    private NavigationGetTool $tool;

    protected function setUp(): void
    {
        $this->navigationRepository = $this->createMock(NavigationRepositoryInterface::class);
        $this->tool = new NavigationGetTool($this->navigationRepository);
    }

    public function testGetNavigationReturnsTree(): void
    {
        $tree = [
            ['title' => 'Home', 'url' => '/', 'children' => []],
            ['title' => 'About', 'url' => '/about', 'children' => []],
        ];

        $this->navigationRepository->expects($this->once())
            ->method('getNavigationTree')
            ->with('main', 'en', 'website', null, 2, ['title' => '', 'url' => ''])
            ->willReturn($tree);

        $result = $this->tool->getNavigation('website', 'en', 'main', 2);

        $this->assertSame($tree, $result['navigation']);
        $this->assertSame('website', $result['webspace']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame('main', $result['context']);
    }

    public function testGetNavigationReturnsErrorOnException(): void
    {
        $this->navigationRepository->method('getNavigationTree')
            ->willThrowException(new \RuntimeException('Invalid webspace'));

        $result = $this->tool->getNavigation('bad', 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('bad', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $this->markTestSkipped('Navigation tool temporarily disabled - MCP attribute commented out');

        // $reflection = new \ReflectionMethod(NavigationGetTool::class, 'getNavigation');
        // $attributes = $reflection->getAttributes(McpTool::class);
        // $this->assertCount(1, $attributes);
        // $this->assertSame('sulu_navigation_get', $attributes[0]->newInstance()->name);
    }
}
