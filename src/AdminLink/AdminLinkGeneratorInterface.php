<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\AdminLink;

interface AdminLinkGeneratorInterface
{
    /**
     * Build an absolute deeplink into the Sulu admin for the given entity, or
     * null when no provider matches, the context is incomplete, or URL
     * generation fails. A missing link must never break a tool response.
     *
     * @param array<string, mixed> $context
     */
    public function generate(string $type, array $context): ?string;
}
