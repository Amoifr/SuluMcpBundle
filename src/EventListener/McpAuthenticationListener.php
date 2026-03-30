<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Returns 401 with WWW-Authenticate header for unauthenticated MCP requests.
 *
 * Per the MCP authorization spec (RFC 9728), the response must include a
 * WWW-Authenticate header with `resource_metadata` pointing to the
 * Protected Resource Metadata (PRM) endpoint so MCP clients can discover
 * the OAuth authorization server.
 *
 * Higher priority (10) than McpExceptionListener (5) so authentication
 * errors are handled before the generic exception handler.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class McpAuthenticationListener implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly string $serverUrl,
        private readonly string $mcpPath = '/_mcp',
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AuthenticationException) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), $this->mcpPath)) {
            return;
        }

        $event->setResponse($this->start($request, $exception));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $response = new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'Unauthorized',
            ],
            'id' => null,
        ], 401);

        $prmUrl = rtrim($this->serverUrl, '/').'/.well-known/oauth-protected-resource';
        $response->headers->set(
            'WWW-Authenticate',
            \sprintf('Bearer resource_metadata="%s", scope="mcp:tools mcp:resources"', $prmUrl)
        );

        return $response;
    }
}
