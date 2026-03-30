<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Entity\ContentGuidelines;
use Sulu\McpServerBundle\Repository\ContentGuidelinesRepositoryInterface;

class UpdateGuidelinesTool
{
    public function __construct(
        private readonly ContentGuidelinesRepositoryInterface $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_update_guidelines',
        description: 'Create or update content guidelines for a webspace (or global defaults when webspace is null or "global"). Only provided fields are updated — omitted fields keep existing values. Total guidelines text should not exceed 2000 characters.',
    )]
    public function updateGuidelines(
        ?string $webspace = null,
        ?string $tone = null,
        ?string $audience = null,
        ?string $style = null,
        ?string $brandRules = null,
        ?string $dos = null,
        ?string $donts = null,
    ): array {
        // Normalize "global" keyword to null for DB storage
        $webspaceKey = ('global' === $webspace) ? null : $webspace;

        $entity = $this->repository->findOneBy(['webspace' => $webspaceKey])
            ?? new ContentGuidelines();

        $entity->setWebspace($webspaceKey);
        if (null !== $tone) {
            $entity->setTone($tone);
        }
        if (null !== $audience) {
            $entity->setAudience($audience);
        }
        if (null !== $style) {
            $entity->setStyle($style);
        }
        if (null !== $brandRules) {
            $entity->setBrandRules($brandRules);
        }
        if (null !== $dos) {
            $entity->setDos($dos);
        }
        if (null !== $donts) {
            $entity->setDonts($donts);
        }

        // Soft size warning (not a hard constraint)
        $totalLength = \strlen((string) $entity->getTone())
            + \strlen((string) $entity->getAudience())
            + \strlen((string) $entity->getStyle())
            + \strlen((string) $entity->getBrandRules())
            + \strlen((string) $entity->getDos())
            + \strlen((string) $entity->getDonts());

        $this->repository->add($entity);
        $this->entityManager->flush();

        $result = [
            'success' => true,
            'webspace' => $webspaceKey ?? 'global',
        ];

        if ($totalLength > 2000) {
            $result['warning'] = \sprintf(
                'Total guidelines text is %d characters. Recommended maximum is 2000 characters for optimal AI context usage.',
                $totalLength
            );
        }

        return $result;
    }
}
