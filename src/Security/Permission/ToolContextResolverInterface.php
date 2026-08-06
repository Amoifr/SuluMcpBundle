<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security\Permission;

/**
 * Resolves a dynamic security context from a tool call's arguments (e.g. the
 * per-group article context from the `template` argument).
 *
 * @internal
 */
interface ToolContextResolverInterface
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function resolve(array $arguments): string;

    /**
     * @return list<string> the possible contexts this resolver can produce
     */
    public function candidates(): array;
}
