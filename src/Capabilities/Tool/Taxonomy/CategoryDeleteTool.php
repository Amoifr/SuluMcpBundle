<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\McpBundle\Security\Attribute\RequiresPermission;
use Sulu\Bundle\McpBundle\Security\Permission\PermissionRequirement;
use Sulu\Component\Security\Authorization\PermissionTypes;

/**
 * @internal
 */
class CategoryDeleteTool
{
    public function __construct(
        private readonly CategoryManagerInterface $categoryManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_category_delete',
        description: 'Delete a category by ID. This removes the category and its children from the tree.',
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement('sulu.settings.categories', PermissionTypes::VIEW),
        new PermissionRequirement('sulu.settings.categories', PermissionTypes::DELETE),
    ])]
    public function deleteCategory(int $id): array
    {
        try {
            $this->categoryManager->delete($id);

            return [
                'success' => true,
                'id' => $id,
                'deleted' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete category %d: %s', $id, $e->getMessage()),
                'hint' => 'Verify the category id exists (use sulu_category_list). Deleting a category also deletes its children.',
            ];
        }
    }
}
