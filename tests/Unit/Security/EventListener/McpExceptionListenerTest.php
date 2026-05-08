<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security\EventListener;

use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\McpServerBundle\Security\EventListener\McpAuthenticationListener;
use Sulu\McpServerBundle\Security\EventListener\McpExceptionListener;
use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class McpExceptionListenerTest extends TestCase
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
