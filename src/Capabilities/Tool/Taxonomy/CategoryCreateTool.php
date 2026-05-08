<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CategoryCreateTool
{
    public function __construct(
        private readonly CategoryManagerInterface $categoryManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_category_create',
        description: 'Create a new category. Categories are hierarchical (tree structure) used to classify content. Pass locale, name, optional key (slug), and optional parentId to nest under an existing category.',
    )]
    public function createCategory(string $locale, string $name, ?string $key = null, ?int $parentId = null): array
    {
        try {
            $user = $this->tokenStorage->getToken()?->getUser();
            if (!$user instanceof UserInterface || !\method_exists($user, 'getId')) {
                return ['error' => 'No authenticated user found'];
            }

            /** @var array<string, mixed> $data */
            $data = ['name' => $name, 'locale' => $locale];
            if (null !== $key) {
                $data['key'] = $key;
            }
            if (null !== $parentId) {
                $data['parent'] = $parentId;
            }

            $category = $this->categoryManager->save($data, $user->getId(), $locale);

            return [
                'success' => true,
                'id' => $category->getId(),
                'name' => $name,
                'key' => $category->getKey(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create category "%s": %s', $name, $e->getMessage()),
            ];
        }
    }
}
