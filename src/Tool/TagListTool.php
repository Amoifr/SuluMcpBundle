<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

class TagListTool
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_tag_list',
        description: 'List all tags. Returns flat array of tag objects with id and name.',
    )]
    public function listTags(): array
    {
        $tags = $this->tagRepository->findAll();

        return [
            'tags' => \array_map(
                fn ($tag) => ['id' => $tag->getId(), 'name' => $tag->getName()],
                $tags,
            ),
        ];
    }
}
