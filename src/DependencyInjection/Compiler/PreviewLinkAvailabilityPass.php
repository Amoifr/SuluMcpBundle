<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\DependencyInjection\Compiler;

use Sulu\Bundle\PreviewBundle\SuluPreviewBundle;
use Sulu\McpServerBundle\Capabilities\Tool\Preview\PreviewLinkGenerateTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes `sulu_preview_link_generate` when SuluPreviewBundle is not loaded.
 *
 * The tool depends on the `sulu_preview.public_preview` route, which only exists
 * when the preview bundle is installed AND its routing is imported (this bundle's
 * config/routes.yaml does that automatically). If the bundle isn't installed at
 * all, exposing the tool would always fail -- drop it from the MCP tool list so
 * AI clients don't discover it.
 *
 * Must run before symfony/mcp-bundle's McpPass so the removed service is absent
 * from the `mcp.tool` tagged-service iterator.
 */
final class PreviewLinkAvailabilityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($this->isPreviewBundleLoaded($container)) {
            return;
        }

        if ($container->hasDefinition(PreviewLinkGenerateTool::class)) {
            $container->removeDefinition(PreviewLinkGenerateTool::class);
        }
    }

    private function isPreviewBundleLoaded(ContainerBuilder $container): bool
    {
        if (!$container->hasParameter('kernel.bundles')) {
            return \class_exists(SuluPreviewBundle::class);
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return \in_array(SuluPreviewBundle::class, $bundles, true)
            || isset($bundles['SuluPreviewBundle']);
    }
}
