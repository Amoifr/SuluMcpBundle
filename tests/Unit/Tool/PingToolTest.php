<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\McpServerBundle\Tool\PingTool;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PingToolTest extends TestCase
{
    private WebspaceManagerInterface&MockObject $webspaceManager;
    private TokenStorageInterface&MockObject $tokenStorage;
    private PingTool $pingTool;

    protected function setUp(): void
    {
        $this->webspaceManager = $this->createMock(WebspaceManagerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->pingTool = new PingTool($this->webspaceManager, $this->tokenStorage);
    }

    public function testPingReturnsStatusOkWithWebspaceList(): void
    {
        $this->setupTokenWithUser('admin');
        $this->setupWebspaceCollection(['example' => ['en', 'de']]);

        $result = $this->pingTool->ping();

        $this->assertSame('ok', $result['status']);
        $this->assertSame('sulu-mcp-server', $result['server']);
        $this->assertSame('admin', $result['user']);
        $this->assertCount(1, $result['webspaces']);
        $this->assertSame('example', $result['webspaces'][0]['key']);
        $this->assertSame(['en', 'de'], $result['webspaces'][0]['locales']);
    }

    public function testPingReturnsNullUserWhenUnauthenticated(): void
    {
        $this->tokenStorage->method('getToken')->willReturn(null);
        $this->setupWebspaceCollection([]);

        $result = $this->pingTool->ping();

        $this->assertNull($result['user']);
    }

    public function testPingMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(PingTool::class, 'ping');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'ping() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_ping', $instance->name);
    }

    private function setupTokenWithUser(string $username): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn($username);
        $this->tokenStorage->method('getToken')->willReturn($token);
    }

    /**
     * @param array<string, list<string>> $webspacesWithLocales
     */
    private function setupWebspaceCollection(array $webspacesWithLocales): void
    {
        $webspaces = [];
        foreach ($webspacesWithLocales as $key => $locales) {
            $localizations = \array_map(function (string $locale) {
                $localization = $this->createMock(Localization::class);
                $localization->method('getLocale')->willReturn($locale);

                return $localization;
            }, $locales);

            $ws = $this->createMock(Webspace::class);
            $ws->method('getKey')->willReturn($key);
            $ws->method('getName')->willReturn($key);
            $ws->method('getAllLocalizations')->willReturn($localizations);
            $webspaces[] = $ws;
        }

        $collection = $this->createMock(WebspaceCollection::class);
        $collection->method('getWebspaces')->willReturn($webspaces);

        $this->webspaceManager->method('getWebspaceCollection')->willReturn($collection);
    }
}
