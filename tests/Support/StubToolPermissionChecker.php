<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Support;

use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;
use Sulu\McpServerBundle\Security\Permission\ToolPermissionCheckerInterface;

/**
 * Stub driven by explicit (context, permission) pairs, so tests can control
 * authorization without touching Sulu's real security stack.
 */
final readonly class StubToolPermissionChecker implements ToolPermissionCheckerInterface
{
    /**
     * @param list<array{0: string, 1: string}> $granted (context, permission) pairs that are granted
     */
    public function __construct(
        private array $granted = [],
    ) {
    }

    public function check(
        string $context,
        string|array $permissions,
        ?string $locale = null,
        ?string $objectType = null,
        mixed $objectId = null,
    ): void {
        foreach ((array) $permissions as $permission) {
            if (!$this->has($context, $permission, $locale, $objectType, $objectId)) {
                throw new PermissionDeniedException($context, $permission, $locale);
            }
        }
    }

    public function has(
        string $context,
        string $permission,
        ?string $locale = null,
        ?string $objectType = null,
        mixed $objectId = null,
    ): bool {
        foreach ($this->granted as [$grantedContext, $grantedPermission]) {
            if ($grantedContext === $context && $grantedPermission === $permission) {
                return true;
            }
        }

        return false;
    }
}
