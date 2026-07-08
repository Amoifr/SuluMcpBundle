<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Security\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Security\OAuth\WellKnownController;

#[CoversClass(WellKnownController::class)]
final class WellKnownControllerTest extends TestCase
{
    public function testProtectedResourceMetadataUsesConfiguredScopesAndMcpPath(): void
    {
        $controller = new WellKnownController('https://sulu.example.com/', '/admin/custom-mcp', ['mcp:tools']);

        $response = $controller->protectedResourceMetadata();
        $body = $this->json($response->getContent());

        self::assertSame('https://sulu.example.com/admin/custom-mcp', $body['resource']);
        self::assertSame(['mcp:tools'], $body['scopes_supported']);
    }

    public function testAuthorizationServerMetadataUsesConfiguredScopes(): void
    {
        $controller = new WellKnownController('https://sulu.example.com', '/admin/_mcp', ['mcp:tools']);

        $response = $controller->authorizationServerMetadata();
        $body = $this->json($response->getContent());

        self::assertSame('https://sulu.example.com/admin/mcp/authorize', $body['authorization_endpoint']);
        self::assertSame('https://sulu.example.com/mcp/token', $body['token_endpoint']);
        self::assertSame(['mcp:tools'], $body['scopes_supported']);
        self::assertContains('none', $body['token_endpoint_auth_methods_supported']);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        self::assertIsString($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);

        return $data;
    }
}
