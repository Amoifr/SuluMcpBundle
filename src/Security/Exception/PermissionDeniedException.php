<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security\Exception;

/**
 * Thrown when a Sulu user lacks the required permission for an MCP operation.
 *
 * Carries structured data (security context, permission type, locale) that
 * McpExceptionListener converts into a JSON-RPC error response.
 *
 * @internal
 */
class PermissionDeniedException extends \RuntimeException
{
    public function __construct(
        private readonly string $securityContext,
        private readonly string $permissionType,
        private readonly ?string $locale = null,
    ) {
        parent::__construct(
            \sprintf(
                'Permission denied: user does not have "%s" permission for security context "%s"%s',
                $permissionType,
                $securityContext,
                null !== $locale ? \sprintf(' in locale "%s"', $locale) : ''
            )
        );
    }

    public function getSecurityContext(): string
    {
        return $this->securityContext;
    }

    public function getPermissionType(): string
    {
        return $this->permissionType;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
