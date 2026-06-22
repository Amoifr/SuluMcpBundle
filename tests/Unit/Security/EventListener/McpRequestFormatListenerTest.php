<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Security\EventListener\McpRequestFormatListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(McpRequestFormatListener::class)]
final class McpRequestFormatListenerTest extends TestCase
{
    private McpRequestFormatListener $listener;

    protected function setUp(): void
    {
        $this->listener = new McpRequestFormatListener('/admin/_mcp');
    }

    private function createRequestEvent(string $pathInfo, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($pathInfo), $type);
    }

    public function testSetsJsonFormatOnMcpPath(): void
    {
        $event = $this->createRequestEvent('/admin/_mcp');

        $this->listener->onKernelRequest($event);

        $this->assertSame('json', $event->getRequest()->getRequestFormat());
    }

    public function testLeavesNonMcpPathUntouched(): void
    {
        $event = $this->createRequestEvent('/admin');

        $this->listener->onKernelRequest($event);

        // Default format is "html" when nothing overrides it.
        $this->assertSame('html', $event->getRequest()->getRequestFormat());
    }

    public function testIgnoresSubRequests(): void
    {
        $event = $this->createRequestEvent('/admin/_mcp', HttpKernelInterface::SUB_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertSame('html', $event->getRequest()->getRequestFormat());
    }
}
