<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security\Permission;

/**
 * @internal
 */
final readonly class ContactSecurityContextResolver implements ToolContextResolverInterface
{
    public function resolve(array $arguments): string
    {
        return 'account' === ($arguments['type'] ?? 'contact')
            ? 'sulu.contact.organizations'
            : 'sulu.contact.people';
    }

    public function candidates(): array
    {
        return ['sulu.contact.people', 'sulu.contact.organizations'];
    }
}
