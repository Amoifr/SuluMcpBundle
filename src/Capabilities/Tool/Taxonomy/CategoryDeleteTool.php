<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;

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
