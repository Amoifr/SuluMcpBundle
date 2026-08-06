<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Capabilities\Tool\Content;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Sulu\Bundle\McpBundle\Capabilities\Tool\ContentTypeResolver;
use Sulu\Bundle\McpBundle\Security\Attribute\RequiresPermission;
use Sulu\Bundle\McpBundle\Security\Exception\PermissionDeniedException;
use Sulu\Bundle\McpBundle\Security\Permission\ArticleSecurityContextResolver;
use Sulu\Bundle\McpBundle\Security\Permission\ContentSecurityContextResolver;
use Sulu\Bundle\McpBundle\Security\Permission\PageDescendantPermissionChecker;
use Sulu\Bundle\McpBundle\Security\Permission\PermissionRequirement;
use Sulu\Bundle\McpBundle\Security\Permission\ToolPermissionCheckerInterface;
use Sulu\Bundle\McpBundle\Security\Permission\WebspacePermissionResolver;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Domain\Model\Page;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class ContentDeleteTool
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly ContentManagerInterface $contentManager,
        private readonly ToolPermissionCheckerInterface $permissionChecker,
        private readonly ContentSecurityContextResolver $contentSecurityContextResolver,
        private readonly PageDescendantPermissionChecker $pageDescendantPermissionChecker,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_content_delete',
        description: 'Permanently delete a page, article, or snippet by UUID. Set "type" to "page", "article", or "snippet". Removes both draft and published versions — this cannot be undone. For pages with children, set forceRemoveChildren=true to delete the whole subtree (ignored for articles/snippets). Snippets may be referenced by other content; deleting one removes that shared content everywhere it is used.',
    )]
    #[RequiresPermission(
        requirements: [
            new PermissionRequirement('#context#', PermissionTypes::EDIT),
            new PermissionRequirement('#context#', PermissionTypes::DELETE),
        ],
        objectResolved: true,
        discoveryContexts: ['sulu.snippet.snippets', ArticleSecurityContextResolver::ANY_ARTICLE_GROUP_CONTEXT, WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT],
    )]
    public function deleteContent(
        string $type,
        string $uuid,
        string $locale,
        bool $forceRemoveChildren = false,
    ): array {
        if (!$this->contentTypeResolver->supports($type)) {
            return [
                'error' => \sprintf('Unsupported content type "%s".', $type),
                'hint' => \sprintf('Supported types: %s.', \implode(', ', $this->contentTypeResolver->supportedTypes())),
            ];
        }

        try {
            $entity = $this->contentTypeResolver->loadDraft($type, $uuid, $locale);
            if (null === $entity) {
                return [
                    'error' => \sprintf('%s not found: %s', \ucfirst($type), $uuid),
                    'hint' => \sprintf('Verify the UUID exists (use sulu_%s_get).', $type),
                ];
            }

            $dimensionContent = 'article' === $type
                ? $this->contentManager->resolve($entity, ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT]) // @phpstan-ignore argument.templateType
                : null;
            $context = $this->contentSecurityContextResolver->forEntity(
                $type,
                $entity,
                $dimensionContent instanceof TemplateInterface ? $dimensionContent : null,
            );

            $this->permissionChecker->check(
                $context,
                [PermissionTypes::EDIT, PermissionTypes::DELETE],
                $locale,
                'page' === $type ? Page::class : null,
                'page' === $type ? $uuid : null,
            );

            if ('page' === $type) {
                $this->pageDescendantPermissionChecker->assertCanDeleteDescendants($uuid);
            }

            $message = $this->contentTypeResolver->createRemoveMessage($type, $uuid, $locale, $forceRemoveChildren);

            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return [
                'success' => true,
                'type' => $type,
                'uuid' => $uuid,
                'deleted' => true,
            ];
        } catch (PermissionDeniedException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete %s %s: %s', $type, $uuid, $e->getMessage()),
                'hint' => \sprintf('Verify the UUID exists (use sulu_%s_get). For a page with children, set forceRemoveChildren=true.', $type),
            ];
        }
    }
}
