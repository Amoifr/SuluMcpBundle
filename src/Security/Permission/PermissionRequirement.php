<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\Permission;

/**
 * One AND-combined permission requirement: a security-context template (which may
 * contain the `#context#` placeholder) and the PermissionTypes constant required.
 */
final readonly class PermissionRequirement
{
    public function __construct(
        public string $contextTemplate,
        public string $permissionType,
    ) {
    }
}
