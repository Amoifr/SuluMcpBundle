<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\CategoryBundle\Api\Category as ApiCategory;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;

class CategoryListTool
{
    public function __construct(
        private readonly CategoryManagerInterface $categoryManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_category_list',
        description: 'List all categories as a tree structure. Returns hierarchical array with nested children. Each category has id, name, key, and children array.',
    )]
    public function listCategories(string $locale): array
    {
        try {
            $entities = $this->categoryManager->findChildrenByParentId(null);
            $apiCategories = $this->categoryManager->getApiObjects($entities, $locale);

            return [
                'categories' => $this->buildTree($apiCategories),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list categories: %s', $e->getMessage()),
            ];
        }
    }

    /**
     * @param iterable<ApiCategory> $categories
     *
     * @return list<array<string, mixed>>
     */
    private function buildTree(iterable $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $node = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'key' => $category->getKey(),
                'children' => $this->buildTree($category->getChildren()),
            ];
            $result[] = $node;
        }

        return $result;
    }
}
