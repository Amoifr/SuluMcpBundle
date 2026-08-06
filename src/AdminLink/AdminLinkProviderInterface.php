<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\AdminLink;

/**
 * @internal
 */
interface AdminLinkProviderInterface
{
    /**
     * The entity type this provider builds links for, e.g. "page" or "article".
     */
    public function getType(): string;

    /**
     * Build the admin SPA hash path (without scheme/host/admin prefix), e.g.
     * "/snippets/en/<uuid>". Returns null when required context is missing.
     *
     * @param array<string, mixed> $context
     */
    public function buildPath(array $context): ?string;
}
