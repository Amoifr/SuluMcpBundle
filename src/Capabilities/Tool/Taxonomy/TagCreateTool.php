<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\McpBundle\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Bundle\McpBundle\Security\Attribute\RequiresPermission;
use Sulu\Bundle\McpBundle\Security\Permission\PermissionRequirement;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;

/**
 * @internal
 */
class TagCreateTool
{
    public function __construct(
        private readonly TagManagerInterface $tagManager,
        private readonly AdminLinkGeneratorInterface $adminLinkGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_tag_create',
        description: 'Create a new tag. Tags are flat labels used to classify content (pages, articles, media). Pass just the tag name.',
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement('sulu.settings.tags', PermissionTypes::EDIT),
        new PermissionRequirement('sulu.settings.tags', PermissionTypes::ADD),
    ])]
    public function createTag(string $name): array
    {
        try {
            $tag = $this->tagManager->save(['name' => $name]);

            $result = [
                'success' => true,
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];

            $adminUrl = $this->adminLinkGenerator->generate('tag', ['id' => $tag->getId()]);
            if (null !== $adminUrl) {
                $result['admin_url'] = $adminUrl;
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create tag "%s": %s', $name, $e->getMessage()),
                'hint' => 'Tag names must be unique. Use sulu_tag_list to check existing tags before creating.',
            ];
        }
    }
}
