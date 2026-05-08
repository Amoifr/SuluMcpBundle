<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;

class PreviewLinkRevokeTool
{
    public function __construct(
        private readonly PreviewLinkManagerInterface $previewLinkManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_preview_link_revoke',
        description: 'Revoke/invalidate a previously generated public preview link for a page or article. After revoking, the preview URL will no longer work.',
    )]
    public function revokePreviewLink(string $resourceKey, string $uuid, string $locale): array
    {
        try {
            $this->previewLinkManager->revoke($resourceKey, $uuid, $locale);

            return [
                'success' => true,
                'action' => 'revoked',
                'resourceKey' => $resourceKey,
                'resourceId' => $uuid,
                'locale' => $locale,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to revoke preview link: %s', $e->getMessage()),
                'hint' => 'Verify a preview link exists for this resource. Use sulu_preview_link_generate to create one first.',
            ];
        }
    }
}
