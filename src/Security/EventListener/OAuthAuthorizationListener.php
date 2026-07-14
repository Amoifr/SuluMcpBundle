<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\EventListener;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Sulu\McpServerBundle\Security\OAuth\OAuthConsentRequest;
use Sulu\McpServerBundle\Security\OAuth\OAuthConsentStore;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
final readonly class OAuthAuthorizationListener
{
    public function __construct(
        private RequestStack $requestStack,
        private OAuthConsentStore $consentStore,
    ) {
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        $requestId = $this->consentStore->getRequestId($request);
        if (null !== $requestId) {
            $decision = $this->consentStore->consumeDecision($request, $requestId);
            if (null !== $decision) {
                $event->resolveAuthorization($decision);

                return;
            }

            if ($this->consentStore->get($request, $requestId) instanceof OAuthConsentRequest) {
                $event->setResponse(new RedirectResponse($this->consentViewUrl($requestId)));

                return;
            }
        }

        $consentRequest = $this->consentStore->create($request, $event);
        $event->setResponse(new RedirectResponse($this->consentViewUrl($consentRequest->getId())));
    }

    private function consentViewUrl(string $requestId): string
    {
        return \sprintf('/admin/#/mcp/authorize/%s', $requestId);
    }
}
