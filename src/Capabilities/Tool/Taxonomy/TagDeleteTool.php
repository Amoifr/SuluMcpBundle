<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;

/**
 * @internal
 */
class TagDeleteTool
{
    public function __construct(
        private readonly TagManagerInterface $tagManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_tag_delete',
        description: 'Delete a tag by ID. This removes the tag but does not affect content that was tagged with it.',
    )]
    public function deleteTag(int $id): array
    {
        try {
            $this->tagManager->delete($id);

            return [
                'success' => true,
                'id' => $id,
                'deleted' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to delete tag %d: %s', $id, $e->getMessage()),
                'hint' => 'Verify the tag id exists (use sulu_tag_list). Deleting a tag does not delete content that referenced it.',
            ];
        }
    }
}
