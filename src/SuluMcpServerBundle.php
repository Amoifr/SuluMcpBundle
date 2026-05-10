<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle;

use Sulu\McpServerBundle\DependencyInjection\Compiler\DangerousToolsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SuluMcpServerBundle extends Bundle
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
    }
}
