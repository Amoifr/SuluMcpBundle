<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle;

use Sulu\Bundle\McpBundle\DependencyInjection\Compiler\DangerousToolsPass;
use Sulu\Bundle\McpBundle\DependencyInjection\Compiler\McpDiscoveryPathPass;
use Sulu\Bundle\McpBundle\DependencyInjection\Compiler\ToolPermissionMapPass;
use Sulu\Bundle\McpBundle\DependencyInjection\Compiler\ToolReferenceHandlerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SuluMcpBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Priority 100 ensures this runs before symfony/mcp-bundle's McpPass
        // (which scans `mcp.tool`-tagged services in BEFORE_OPTIMIZATION).
        $container->addCompilerPass(new DangerousToolsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new ToolPermissionMapPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 90);
        $container->addCompilerPass(new ToolReferenceHandlerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 80);
        $container->addCompilerPass(new McpDiscoveryPathPass());
    }
}
