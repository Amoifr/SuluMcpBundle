<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Media;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
class MediaUpdateTool
{
    public function __construct(
        private readonly MediaManagerInterface $mediaManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_media_update',
        description: 'Update media metadata (title, description, copyright). Does not change the file itself — only metadata fields. Pass only the fields you want to change.',
    )]
    public function updateMedia(
        int $id,
        string $locale,
        ?string $title = null,
        ?string $description = null,
        ?string $copyright = null,
    ): array {
        try {
            $user = $this->tokenStorage->getToken()?->getUser();

            if (!$user instanceof User) {
                return [
                    'error' => 'Not authenticated — a valid Sulu user is required to update media.',
                    'hint' => 'Authenticate as a Sulu user with permission to edit media before retrying.',
                ];
            }

            $data = [
                'id' => $id,
                'locale' => $locale,
            ];

            if (null !== $title) {
                $data['title'] = $title;
            }

            if (null !== $description) {
                $data['description'] = $description;
            }

            if (null !== $copyright) {
                $data['copyright'] = $copyright;
            }

            $media = $this->mediaManager->save(null, $data, $user->getId());

            return [
                'success' => true,
                'id' => $media->getId(),
                'title' => $media->getTitle(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update media %d: %s', $id, $e->getMessage()),
                'hint' => 'Verify the media id exists (use sulu_media_list) and the locale is valid.',
            ];
        }
    }
}
