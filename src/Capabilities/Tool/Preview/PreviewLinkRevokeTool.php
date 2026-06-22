<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;

/**
 * @internal
 */
class PreviewLinkRevokeTool
{
    private const TYPE_MAP = ['page' => 'pages', 'article' => 'articles'];

    public function __construct(
        private readonly PreviewLinkManagerInterface $previewLinkManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_preview_link_revoke',
        description: 'Revoke/invalidate a previously generated public preview link for a page or article. After revoking, the preview URL will no longer work. Pass `type` as "page" or "article" (the same singular values used by the other tools). If no preview link exists for the resource, the operation returns an error — verify a link exists with sulu_preview_link_generate before revoking.',
    )]
    public function revokePreviewLink(
        #[Schema(description: 'Content type to preview: "page" or "article" (same singular values used by the other tools).', enum: ['page', 'article'])]
        string $type,
        string $uuid,
        string $locale,
    ): array {
        try {
            $resourceKey = self::TYPE_MAP[$type] ?? $type;
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
