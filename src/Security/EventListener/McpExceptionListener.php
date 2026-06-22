<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Security\EventListener;

use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts MCP-endpoint exceptions to JSON-RPC error responses:
 * PermissionDeniedException -> 403, InvalidArgumentException -> 400,
 * anything else -> 500. Non-MCP requests fall through to Symfony.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 5)]
class McpExceptionListener
{
    public function __construct(
        private readonly string $mcpPath = '/admin/_mcp',
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), $this->mcpPath)) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof PermissionDeniedException) {
            $response = new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Permission denied',
                    'data' => [
                        'type' => 'permission_denied',
                        'detail' => $exception->getMessage(),
                        'required_permission' => $exception->getSecurityContext(),
                        'permission_type' => $exception->getPermissionType(),
                        'locale' => $exception->getLocale(),
                    ],
                ],
                'id' => null,
            ], 403);

            $event->setResponse($response);

            return;
        }

        if ($exception instanceof \InvalidArgumentException) {
            $response = new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => [
                        'type' => 'invalid_params',
                        'detail' => $exception->getMessage(),
                    ],
                ],
                'id' => null,
            ], 400);

            $event->setResponse($response);

            return;
        }

        // Generic internal error for unexpected exceptions
        $response = new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32603,
                'message' => 'Internal error',
                'data' => [
                    'type' => 'internal_error',
                    'detail' => $exception->getMessage(),
                ],
            ],
            'id' => null,
        ], 500);

        $event->setResponse($response);
    }
}
