<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PreviewLinkGenerateTool
{
    public function __construct(
        private readonly PreviewLinkManagerInterface $previewLinkManager,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_preview_link_generate',
        description: 'Generate a shareable public preview URL for a draft page or article. The URL can be viewed without CMS login, useful for sharing drafts with reviewers. For pages, the webspace parameter is required. For articles, webspace is not needed.',
    )]
    public function generatePreviewLink(string $resourceKey, string $uuid, string $locale, ?string $webspace = null): array
    {
        try {
            $options = [];
            if (null !== $webspace) {
                $options['webspaceKey'] = $webspace;
            }

            $previewLink = $this->previewLinkManager->generate($resourceKey, $uuid, $locale, $options);

            $url = $this->router->generate(
                'sulu_preview.public_preview',
                ['token' => $previewLink->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            return [
                'success' => true,
                'preview_url' => $url,
                'token' => $previewLink->getToken(),
                'resourceKey' => $previewLink->getResourceKey(),
                'resourceId' => $previewLink->getResourceId(),
                'locale' => $previewLink->getLocale(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to generate preview link: %s', $e->getMessage()),
                'hint' => 'Verify the resource exists and the resourceKey is correct (pages or articles). For pages, webspace parameter is required.',
            ];
        }
    }
}
