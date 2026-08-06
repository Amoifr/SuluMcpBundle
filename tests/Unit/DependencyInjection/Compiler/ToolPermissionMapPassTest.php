<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\DependencyInjection\Compiler;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\McpServerBundle\Capabilities\Tool\PingTool;
use Sulu\McpServerBundle\DependencyInjection\Compiler\ToolPermissionMapPass;
use Sulu\McpServerBundle\Security\Attribute\RequiresPermission;
use Sulu\McpServerBundle\Security\Permission\PermissionRequirement;

#[CoversClass(ToolPermissionMapPass::class)]
final class ToolPermissionMapPassTest extends TestCase
{
    public function testExtractReadsRequiresPermission(): void
    {
        $entry = ToolPermissionMapPass::extract(FixturePermissionTool::class);

        self::assertNotNull($entry);
        self::assertSame('fixture_permission_tool', $entry['name']);
        self::assertContains(
            ['context' => 'sulu.settings.tags', 'permission' => 'edit'],
            $entry['requirements'],
        );
        self::assertContains(
            ['context' => 'sulu.settings.tags', 'permission' => 'add'],
            $entry['requirements'],
        );
        self::assertFalse($entry['objectResolved']);
        self::assertSame([], $entry['discoveryContexts']);
    }

    public function testExtractReturnsNullWithoutRequiresPermission(): void
    {
        self::assertNull(ToolPermissionMapPass::extract(PingTool::class));
    }
}

final class FixturePermissionTool
{
    #[McpTool(name: 'fixture_permission_tool')]
    #[RequiresPermission(requirements: [
        new PermissionRequirement('sulu.settings.tags', PermissionTypes::EDIT),
        new PermissionRequirement('sulu.settings.tags', PermissionTypes::ADD),
    ])]
    public function run(): array
    {
        return [];
    }
}
