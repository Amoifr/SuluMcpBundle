<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Application;

use League\Bundle\OAuth2ServerBundle\LeagueOAuth2ServerBundle;
use Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Sulu\McpServerBundle\SuluMcpServerBundle;
use Sulu\Snippet\Infrastructure\Symfony\HttpKernel\SuluSnippetBundle;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * AppKernel for the bundle's functional tests.
 */
class Kernel extends SuluTestKernel
{
    public function registerBundles(): iterable
    {
        $bundles = [...parent::registerBundles()];

        $bundles[] = new SuluArticleBundle();
        $bundles[] = new SuluSnippetBundle();
        $bundles[] = new McpBundle();
        $bundles[] = new LeagueOAuth2ServerBundle();
        $bundles[] = new SuluMcpServerBundle();

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(__DIR__.'/config/config.yml');
    }
}
