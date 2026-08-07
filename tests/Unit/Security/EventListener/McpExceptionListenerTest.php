<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Tests\Unit\Security\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\McpBundle\Security\EventListener\McpAuthenticationListener;
use Sulu\Bundle\McpBundle\Security\EventListener\McpExceptionListener;
use Sulu\Bundle\McpBundle\Security\Exception\PermissionDeniedException;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[CoversClass(McpExceptionListener::class)]
#[CoversClass(McpAuthenticationListener::class)]
final class McpExceptionListenerTest extends TestCase
{
    private McpExceptionListener $listener;
    private McpAuthenticationListener $authListener;

    protected function setUp(): void
    {
        $this->listener = new McpExceptionListener('/_mcp');
        $this->authListener = new McpAuthenticationListener('https://sulu.example.com', '/_mcp');
    }

    private function createExceptionEvent(\Throwable $exception, string $pathInfo = '/_mcp'): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($pathInfo);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    public function testPermissionDeniedExceptionReturns403WithPermissionDeniedType(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');
        $event = $this->createExceptionEvent($exception);

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(-32603, $body['error']['code']);
        $this->assertSame('permission_denied', $body['error']['data']['type']);
        $this->assertSame('sulu.webspaces.example', $body['error']['data']['required_permission']);
    }

    public function testInvalidArgumentExceptionReturns400WithInvalidParamsType(): void
    {
        $exception = new \InvalidArgumentException('Invalid webspace "foo"');
        $event = $this->createExceptionEvent($exception);

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(-32602, $body['error']['code']);
        $this->assertSame('invalid_params', $body['error']['data']['type']);
    }

    public function testGenericExceptionReturns500WithInternalErrorType(): void
    {
        $exception = new \RuntimeException('Something went wrong');
        $event = $this->createExceptionEvent($exception);

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(-32603, $body['error']['code']);
        $this->assertSame('internal_error', $body['error']['data']['type']);
    }

    public function testAccessDeniedExceptionIsLeftToTheSecurityLayer(): void
    {
        // Thrown by Symfony's AccessListener when the access_control rule on the
        // MCP path denies an unauthenticated request. Swallowing it here would
        // pre-empt the firewall's ExceptionListener (priority 1), which turns it
        // into the RFC 9728 401 via the configured entry point.
        $exception = new AccessDeniedException('Access Denied. The user is not appropriately authenticated.');
        $event = $this->createExceptionEvent($exception);

        $this->listener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testAuthenticationExceptionIsLeftToTheAuthenticationListener(): void
    {
        $exception = new AuthenticationException('Full authentication is required');
        $event = $this->createExceptionEvent($exception);

        $this->listener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testExceptionOnNonMcpPathDoesNotSetResponse(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');
        $event = $this->createExceptionEvent($exception, '/admin');

        $this->listener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testAuthenticationExceptionOnMcpPathReturns401WithWwwAuthenticate(): void
    {
        $exception = new AuthenticationException('Full authentication is required');
        $event = $this->createExceptionEvent($exception);

        $this->authListener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());

        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($wwwAuth);
        $this->assertStringContainsString('oauth-protected-resource', $wwwAuth);
        $this->assertStringContainsString('https://sulu.example.com', $wwwAuth);

        $body = json_decode($response->getContent(), true);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame(-32001, $body['error']['code']);
    }

    public function testAuthenticationExceptionOnNonMcpPathDoesNotSetResponse(): void
    {
        $exception = new AuthenticationException('Full authentication is required');
        $event = $this->createExceptionEvent($exception, '/admin');

        $this->authListener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testNonAuthenticationExceptionDoesNotTriggerAuthListener(): void
    {
        $exception = new \RuntimeException('Something else');
        $event = $this->createExceptionEvent($exception);

        $this->authListener->onKernelException($event);

        $this->assertNull($event->getResponse());
    }
}
