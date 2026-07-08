<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\DependencyInjection\Configuration;
use Sulu\McpServerBundle\DependencyInjection\SuluMcpServerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[CoversClass(SuluMcpServerExtension::class)]
#[CoversClass(Configuration::class)]
final class SuluMcpServerExtensionTest extends TestCase
{
    public function testPrependWiresMcpAndLeagueOAuthConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('mcp'));
        $container->registerExtension($this->extension('league_oauth2_server'));
        $container->registerExtension(new SuluMcpServerExtension());
        $container->loadFromExtension('sulu_mcp_server', [
            'server_url' => 'https://sulu.example.com',
            'mcp_path' => '/admin/custom-mcp',
            'oauth' => [
                'access_token_ttl' => 120,
                'refresh_token_ttl' => 240,
                'scopes' => ['mcp:tools'],
            ],
        ]);

        (new SuluMcpServerExtension())->prepend($container);

        self::assertSame(
            [
                [
                    'client_transports' => ['http' => true],
                    'http' => ['path' => '/admin/custom-mcp'],
                    'discovery' => ['scan_dirs' => ['src', 'vendor/sulu/mcp-server-bundle/src']],
                ],
            ],
            $container->getExtensionConfig('mcp'),
        );

        self::assertSame(
            [
                [
                    'authorization_server' => [
                        'access_token_ttl' => 'PT120S',
                        'refresh_token_ttl' => 'PT240S',
                        'require_code_challenge_for_public_clients' => true,
                    ],
                    'scopes' => [
                        'available' => ['mcp:tools'],
                        'default' => ['mcp:tools'],
                    ],
                ],
            ],
            $container->getExtensionConfig('league_oauth2_server'),
        );
    }

    private function extension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(
                private readonly string $alias,
            ) {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        };
    }
}
