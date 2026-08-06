<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\AdminLink;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
final readonly class AdminLinkGenerator implements AdminLinkGeneratorInterface
{
    /**
     * @param iterable<AdminLinkProviderInterface> $providers
     */
    public function __construct(
        private RouterInterface $router,
        private iterable $providers,
    ) {
    }

    /**
     * Build an absolute deeplink into the Sulu admin for the given entity, or
     * null when no provider matches, the context is incomplete, or URL
     * generation fails. A missing link must never break a tool response.
     *
     * @param array<string, mixed> $context
     */
    public function generate(string $type, array $context): ?string
    {
        try {
            foreach ($this->providers as $provider) {
                if ($provider->getType() !== $type) {
                    continue;
                }

                $path = $provider->buildPath($context);
                if (null === $path) {
                    return null;
                }

                $base = \rtrim($this->router->generate('sulu_admin', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');

                return $base.'/#'.$path;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
