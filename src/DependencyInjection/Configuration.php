<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sulu_mcp_server');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('server_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Public base URL of the Sulu installation (e.g., https://sulu.example.com)')
                ->end()
                ->scalarNode('mcp_path')
                    ->defaultValue('/_mcp')
                    ->info('MCP endpoint path')
                ->end()
                ->arrayNode('oauth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('access_token_ttl')
                            ->defaultValue(3600)
                            ->info('Access token lifetime in seconds')
                        ->end()
                        ->integerNode('refresh_token_ttl')
                            ->defaultValue(2592000)
                            ->info('Refresh token lifetime in seconds (default: 30 days)')
                        ->end()
                        ->arrayNode('scopes')
                            ->scalarPrototype()->end()
                            ->defaultValue(['mcp:tools', 'mcp:resources'])
                            ->info('OAuth scopes supported by the MCP server')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
