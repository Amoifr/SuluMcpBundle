<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Media;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

/**
 * @internal
 */
class MediaListTool
{
    public function __construct(
        private readonly MediaManagerInterface $mediaManager,
    ) {
    }

    /**
     * @param string[]|null $types
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_media_list',
        description: 'List/search media files. Filter by collection ID, media types, or search text. Note: tag-based filtering is not supported — use search text instead. Returns paginated list with total count.',
    )]
    public function listMedia(
        string $locale,
        ?int $collectionId = null,
        ?string $search = null,
        #[Schema(type: 'array', description: 'Filter by media type name(s). Typical values: "image", "video", "audio", "document" (the type names configured in this Sulu install). Omit for all types.', items: ['type' => 'string'])]
        ?array $types = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        $offset = ($page - 1) * $limit;

        $filter = [];

        if (null !== $collectionId) {
            $filter['collection'] = $collectionId;
        }

        if (null !== $search) {
            $filter['search'] = $search;
        }

        if (null !== $types) {
            $filter['types'] = $types;
        }

        $media = $this->mediaManager->get($locale, $filter, $limit, $offset);
        $total = $this->mediaManager->getCount();

        $results = [];
        foreach ($media as $m) {
            $results[] = [
                'id' => $m->getId(),
                'title' => $m->getTitle(),
                'mimeType' => $m->getMimeType(),
                'size' => $m->getSize(),
                'url' => $m->getUrl(),
            ];
        }

        return [
            'media' => $results,
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ];
    }
}
