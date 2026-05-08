<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\DependencyInjection\Compiler;

use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleBlockRemoveTool;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleDeleteTool;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticlePublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\Article\ArticleUnpublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\Page\BlockRemoveTool;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageDeleteTool;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PagePublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\Page\PageUnpublishTool;
use Sulu\McpServerBundle\Capabilities\Tool\Preview\PreviewLinkRevokeTool;
use Sulu\McpServerBundle\Capabilities\Tool\Taxonomy\CategoryDeleteTool;
use Sulu\McpServerBundle\Capabilities\Tool\Taxonomy\TagDeleteTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes tool service definitions for dangerous categories that are not enabled
 * in bundle configuration. Must run before symfony/mcp-bundle's McpPass so the
 * removed services are absent from the `mcp.tool` tagged-service iterator.
 */
final class DangerousToolsPass implements CompilerPassInterface
{
    /**
     * @var array<string, list<class-string>>
     */
    private const TOOLS_BY_CATEGORY = [
        'delete' => [
            PageDeleteTool::class,
            ArticleDeleteTool::class,
            TagDeleteTool::class,
            CategoryDeleteTool::class,
        ],
        'publish' => [
            PagePublishTool::class,
            PageUnpublishTool::class,
            ArticlePublishTool::class,
            ArticleUnpublishTool::class,
            PreviewLinkRevokeTool::class,
        ],
        'block_remove' => [
            BlockRemoveTool::class,
            ArticleBlockRemoveTool::class,
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::TOOLS_BY_CATEGORY as $category => $classes) {
            $parameter = \sprintf('sulu_mcp_server.dangerous_tools.%s', $category);
            if (!$container->hasParameter($parameter) || true === $container->getParameter($parameter)) {
                continue;
            }

            foreach ($classes as $class) {
                if ($container->hasDefinition($class)) {
                    $container->removeDefinition($class);
                }
            }
        }
    }
}
