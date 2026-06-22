<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;

/**
 * @internal
 */
class TagCreateTool
{
    public function __construct(
        private readonly TagManagerInterface $tagManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_tag_create',
        description: 'Create a new tag. Tags are flat labels used to classify content (pages, articles, media). Pass just the tag name.',
    )]
    public function createTag(string $name): array
    {
        try {
            $tag = $this->tagManager->save(['name' => $name]);

            return [
                'success' => true,
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create tag "%s": %s', $name, $e->getMessage()),
                'hint' => 'Tag names must be unique. Use sulu_tag_list to check existing tags before creating.',
            ];
        }
    }
}
