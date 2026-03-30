<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;

class PermissionDeniedExceptionTest extends TestCase
{
    public function testGetSecurityContextReturnsContext(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');

        $this->assertSame('sulu.webspaces.example', $exception->getSecurityContext());
    }

    public function testGetPermissionTypeReturnsType(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');

        $this->assertSame(PermissionTypes::VIEW, $exception->getPermissionType());
    }

    public function testGetLocaleReturnsLocale(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');

        $this->assertSame('en', $exception->getLocale());
    }

    public function testMessageContainsSecurityContextAndPermissionType(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW, 'en');

        $this->assertStringContainsString('sulu.webspaces.example', $exception->getMessage());
        $this->assertStringContainsString((string) PermissionTypes::VIEW, $exception->getMessage());
    }

    public function testLocaleIsOptional(): void
    {
        $exception = new PermissionDeniedException('sulu.webspaces.example', PermissionTypes::VIEW);

        $this->assertNull($exception->getLocale());
        $this->assertStringContainsString('sulu.webspaces.example', $exception->getMessage());
    }
}
