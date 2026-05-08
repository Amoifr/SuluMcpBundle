<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Media;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

class MediaGetTool
{
    public function __construct(
        private readonly MediaManagerInterface $mediaManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_media_get',
        description: 'Get detailed information about a media file by ID. Returns metadata (title, description, copyright, mime type, size), the original URL, and all available format/thumbnail URLs.',
    )]
    public function getMedia(int $id, string $locale): array
    {
        try {
            $media = $this->mediaManager->getById($id, $locale);

            return [
                'id' => $media->getId(),
                'title' => $media->getTitle(),
                'description' => $media->getDescription(),
                'copyright' => $media->getCopyright(),
                'mimeType' => $media->getMimeType(),
                'size' => $media->getSize(),
                'url' => $media->getUrl(),
                'formats' => $media->getFormats(),
            ];
        } catch (\Throwable) {
            return [
                'error' => \sprintf('Media not found: %d', $id),
            ];
        }
    }
}
