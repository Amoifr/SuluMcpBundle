<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\Permission;

use Sulu\Component\Security\Authentication\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Builds the `accessControl` filter Sulu's repositories accept, so per-object ACLs
 * are applied in the query instead of after it. Without it a listing returns rows
 * the caller may not open individually, and counts them in `total`.
 */
final readonly class AccessControlFilterFactory
{
    /**
     * @param array<string, int> $permissions
     */
    public function __construct(
        private ?Security $security,
        private array $permissions,
    ) {
    }

    /**
     * @return array{user: UserInterface|null, permission: int}
     */
    public function forPermission(string $permissionType): array
    {
        $user = $this->security?->getUser();

        return [
            'user' => $user instanceof UserInterface ? $user : null,
            'permission' => $this->permissions[$permissionType] ?? 0,
        ];
    }
}
