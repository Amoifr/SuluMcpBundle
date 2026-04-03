<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\EventListener;

use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sets the Sulu system context for MCP endpoint requests.
 *
 * The Sulu UserProvider requires a system to be set in the SystemStore
 * to verify that the user has a role in that system. For admin requests,
 * Sulu sets this via SuluAdminRequestListener. For MCP requests (which
 * go through the OAuth firewall, not the admin firewall), no system is
 * set by default — causing UserNotFoundException even for valid users.
 *
 * This listener sets the system on kernel.request (before authentication),
 * so the UserProvider can find users when the OAuth authenticator loads them.
 */
class OAuthSystemStoreListener
{
    public function __construct(
        private readonly SystemStoreInterface $systemStore,
        private readonly string $mcpPath,
        private readonly string $defaultSystem = 'Sulu',
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, $this->mcpPath)) {
            return;
        }

        $this->systemStore->setSystem($this->defaultSystem);
    }
}
