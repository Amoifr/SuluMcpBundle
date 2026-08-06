<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Preview;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Sulu\Bundle\PreviewBundle\Application\Manager\PreviewLinkManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\McpServerBundle\Security\Attribute\RequiresPermission;
use Sulu\McpServerBundle\Security\Exception\PermissionDeniedException;
use Sulu\McpServerBundle\Security\Permission\ArticleSecurityContextResolver;
use Sulu\McpServerBundle\Security\Permission\ContentSecurityContextResolver;
use Sulu\McpServerBundle\Security\Permission\PermissionRequirement;
use Sulu\McpServerBundle\Security\Permission\ToolPermissionCheckerInterface;
use Sulu\McpServerBundle\Security\Permission\WebspacePermissionResolver;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class PreviewLinkGenerateTool
{
    private const TYPE_MAP = ['page' => 'pages', 'article' => 'articles'];

    public function __construct(
        private readonly PreviewLinkManagerInterface $previewLinkManager,
        private readonly RouterInterface $router,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly ContentManagerInterface $contentManager,
        private readonly ToolPermissionCheckerInterface $permissionChecker,
        private readonly ContentSecurityContextResolver $contentSecurityContextResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_preview_link_generate',
        description: 'Generate a shareable public preview URL for a draft page or article. Returns a token-protected URL under /admin/p/<token> that reviewers can open without a CMS login. The `webspace` parameter is REQUIRED for both pages and articles -- Sulu\'s preview renderer needs to know which webspace context (theme, routes, templates) to render the preview under, and articles that aren\'t scoped to a webspace at generation time produce a token that crashes when opened. Use sulu_ping or sulu_get_context to list the available webspaces. Pass `type` as "page" or "article" (the same singular values used by the other tools).',
    )]
    #[RequiresPermission(
        requirements: [new PermissionRequirement('#context#', PermissionTypes::EDIT)],
        objectResolved: true,
        discoveryContexts: [ArticleSecurityContextResolver::ANY_ARTICLE_GROUP_CONTEXT, WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT],
    )]
    public function generatePreviewLink(
        #[Schema(description: 'Content type to preview: "page" or "article" (same singular values used by the other tools).', enum: ['page', 'article'])]
        string $type,
        string $uuid,
        string $locale,
        ?string $webspace = null,
    ): array {
        if (null === $webspace || '' === $webspace) {
            return [
                'error' => 'Missing required parameter "webspace". Sulu\'s preview renderer needs a webspace key to set up the request context (theme, routes, templates), and the stored preview link will crash when opened without it. Pass the webspace key, e.g. "sulu". Use sulu_ping to list available webspaces.',
                'hint' => 'For articles that are reachable in multiple webspaces, pick the one whose theme should render the preview.',
            ];
        }

        try {
            $entity = $this->contentTypeResolver->loadDraft($type, $uuid, $locale);
            if (null === $entity) {
                return [
                    'error' => \sprintf('%s not found: %s', $type, $uuid),
                    'hint' => 'Verify the type ("page"/"article"), uuid and locale.',
                ];
            }

            $dimensionContent = 'article' === $type
                ? $this->contentManager->resolve($entity, ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT]) // @phpstan-ignore argument.templateType
                : null;

            // Preview links are gated on EDIT, stricter than the admin UI's VIEW.
            $this->permissionChecker->check(
                $this->contentSecurityContextResolver->forEntity(
                    $type,
                    $entity,
                    $dimensionContent instanceof TemplateInterface ? $dimensionContent : null,
                ),
                PermissionTypes::EDIT,
                $locale,
                'page' === $type ? Page::class : null,
                'page' === $type ? $uuid : null,
            );

            // The token is rendered later under this webspace's portal/theme/routes, so
            // it is a context the caller must be allowed to use -- not just a label.
            if ('page' === $type && $entity instanceof PageInterface && $webspace !== $entity->getWebspaceKey()) {
                throw new PermissionDeniedException('sulu.webspaces.'.$webspace, PermissionTypes::EDIT, $locale);
            }
            $this->permissionChecker->check('sulu.webspaces.'.$webspace, PermissionTypes::EDIT, $locale);

            $resourceKey = self::TYPE_MAP[$type] ?? $type;
            $options = ['webspaceKey' => $webspace];

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
        } catch (PermissionDeniedException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        } catch (RouteNotFoundException) {
            return [
                'error' => 'Public preview route `sulu_preview.public_preview` is not registered. Import this bundle\'s config/routes.yaml in the host project\'s routing (it pulls in @SuluPreviewBundle/Resources/config/routing_public.yaml under /admin/p).',
                'hint' => 'Without the public preview route, only admin-only preview is available -- which cannot be shared via MCP.',
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to generate preview link: %s', $e->getMessage()),
                'hint' => 'Verify the resource exists, the type is correct ("page" or "article"), and the webspace is valid (use sulu_ping to list webspaces).',
            ];
        }
    }
}
