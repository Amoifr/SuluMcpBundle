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
 *
 * Mcp also performs runtime attribute discovery that registers every
 * `#[McpTool]`-tagged class regardless of DI -- removing the service alone is
 * not enough. The pass therefore also publishes the disabled tool NAMES as a
 * container parameter, consumed by `FilteredRegistry` to drop the same tools
 * from the discovery state at runtime.
 */
final class DangerousToolsPass implements CompilerPassInterface
{
    /**
     * Map of dangerous-tools category -> [class-string => mcp tool name].
     * The tool name matches each class's `#[McpTool(name: ...)]` attribute.
     *
     * @var array<string, array<class-string, string>>
     */
    private const TOOLS_BY_CATEGORY = [
        'delete' => [
            PageDeleteTool::class => 'sulu_page_delete',
            ArticleDeleteTool::class => 'sulu_article_delete',
            TagDeleteTool::class => 'sulu_tag_delete',
            CategoryDeleteTool::class => 'sulu_category_delete',
        ],
        'publish' => [
            PagePublishTool::class => 'sulu_page_publish',
            PageUnpublishTool::class => 'sulu_page_unpublish',
            ArticlePublishTool::class => 'sulu_article_publish',
            ArticleUnpublishTool::class => 'sulu_article_unpublish',
            PreviewLinkRevokeTool::class => 'sulu_preview_link_revoke',
        ],
        'block_remove' => [
            BlockRemoveTool::class => 'sulu_block_remove',
            ArticleBlockRemoveTool::class => 'sulu_article_block_remove',
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::TOOLS_BY_CATEGORY as $category => $tools) {
            $parameter = \sprintf('sulu_mcp_server.dangerous_tools.%s', $category);
            if (!$container->hasParameter($parameter) || true === $container->getParameter($parameter)) {
                continue;
            }

            foreach (array_keys($tools) as $class) {
                if ($container->hasDefinition($class)) {
                    $container->removeDefinition($class);
                }
            }
        }
    }

    /**
     * Resolve the list of MCP tool names that must be hidden given the bundle's
     * `dangerous_tools` configuration. Called from the bundle extension to
     * populate the `sulu_mcp_server.disabled_tool_names` parameter used by
     * `FilteredRegistry`.
     *
     * @param array<string, bool> $dangerousToolsConfig
     *
     * @return list<string>
     */
    public static function resolveDisabledToolNames(array $dangerousToolsConfig): array
    {
        $names = [];
        foreach (self::TOOLS_BY_CATEGORY as $category => $tools) {
            if (true === ($dangerousToolsConfig[$category] ?? false)) {
                continue;
            }

            foreach ($tools as $toolName) {
                $names[] = $toolName;
            }
        }

        return $names;
    }
}
