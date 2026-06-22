<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\EventListener;

use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sets the Sulu system on kernel.request for MCP requests.
 *
 * Sulu's UserProvider needs a system in the SystemStore to check the user's role.
 * SuluAdminRequestListener sets it for admin requests, but MCP uses the OAuth
 * firewall — without this, valid users hit UserNotFoundException.
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
