<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Redirects unauthenticated OAuth-authorize hits to the Sulu admin login
 * (McpLoginSuccessListener resumes the flow afterwards). All other admin
 * routes delegate to Sulu's original entry point.
 *
 * @internal
 */
class McpAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AuthenticationEntryPointInterface $inner,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (!str_contains($request->getPathInfo(), '/mcp/authorize')) {
            return $this->inner->start($request, $authException);
        }

        return new RedirectResponse('/admin/');
    }
}
