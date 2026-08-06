<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security\Permission;

use Sulu\Bundle\McpBundle\Security\Exception\PermissionDeniedException;

/**
 * Checks a Sulu security-context permission for the current user, failing
 * closed on empty subjects, unresolved contexts, or missing authentication.
 *
 * @internal
 */
interface ToolPermissionCheckerInterface
{
    /**
     * @param string|list<string> $permissions every listed permission must be granted
     *
     * @throws PermissionDeniedException
     */
    public function check(
        string $context,
        string|array $permissions,
        ?string $locale = null,
        ?string $objectType = null,
        mixed $objectId = null,
    ): void;

    public function has(
        string $context,
        string $permission,
        ?string $locale = null,
        ?string $objectType = null,
        mixed $objectId = null,
    ): bool;
}
