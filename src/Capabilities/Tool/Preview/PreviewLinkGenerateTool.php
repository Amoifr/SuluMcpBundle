<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
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
        description: 'Generate a shareable public preview URL for a draft page or article. Returns a token-protected URL under /admin/p/<token> that reviewers can open without a CMS login. Requires Sulu\'s public preview route to be registered; this bundle imports it automatically when its config/routes.yaml is loaded. For pages, the webspace parameter is required. For articles, webspace is optional.',
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
        } catch (RouteNotFoundException) {
            return [
                'error' => 'Public preview route `sulu_preview.public_preview` is not registered. Import this bundle\'s config/routes.yaml in the host project\'s routing (it pulls in @SuluPreviewBundle/Resources/config/routing_public.yaml under /admin/p).',
                'hint' => 'Without the public preview route, only admin-only preview is available -- which cannot be shared via MCP.',
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to generate preview link: %s', $e->getMessage()),
                'hint' => 'Verify the resource exists and the resourceKey is correct (pages or articles). For pages, webspace parameter is required.',
            ];
        }
    }
}
