<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PingTool
{
    public function __construct(
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_ping',
        description: 'Verify MCP connection and authentication. Returns server info, the authenticated user, and available webspaces.',
    )]
    public function ping(): array
    {
        $token = $this->tokenStorage->getToken();
        $username = $token?->getUserIdentifier();

        $result = [
            'status' => 'ok',
            'server' => 'sulu-mcp-server',
            'version' => '1.0.0',
            'user' => $username,
            'webspaces' => [],
        ];

        $webspaces = $this->webspaceManager->getWebspaceCollection()->getWebspaces();
        foreach ($webspaces as $webspace) {
            $locales = \array_map(
                fn ($l) => $l->getLocale(),
                $webspace->getAllLocalizations()
            );
            $result['webspaces'][] = [
                'key' => $webspace->getKey(),
                'name' => $webspace->getName(),
                'locales' => $locales,
            ];
        }

        return $result;
    }
}
