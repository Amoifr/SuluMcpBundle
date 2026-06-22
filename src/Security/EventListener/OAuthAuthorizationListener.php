<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\EventListener;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Auto-approves OAuth authorization for users authenticated via the Sulu admin
 * firewall, setting the OAuth user from the security token (no consent screen).
 */
#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
class OAuthAuthorizationListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $event->setUser($user);
        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
    }
}
