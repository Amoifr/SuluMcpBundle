<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluMcpServerExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('mcp')) {
            $container->prependExtensionConfig('mcp', [
                'client_transports' => [
                    'http' => true,
                ],
                'discovery' => [
                    'scan_dirs' => ['src', 'vendor/sulu/mcp-server-bundle/src'],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('sulu_mcp_server.server_url', $config['server_url']);
        $container->setParameter('sulu_mcp_server.mcp_path', $config['mcp_path']);
        $container->setParameter('sulu_mcp_server.oauth.access_token_ttl', $config['oauth']['access_token_ttl']);
        $container->setParameter('sulu_mcp_server.oauth.refresh_token_ttl', $config['oauth']['refresh_token_ttl']);
        $container->setParameter('sulu_mcp_server.oauth.scopes', $config['oauth']['scopes']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 2).'/config')
        );
        $loader->load('services.yaml');
    }
}
