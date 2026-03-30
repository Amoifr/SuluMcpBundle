<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Sulu\McpServerBundle\Entity\ContentGuidelines;
use Sulu\McpServerBundle\Repository\ContentGuidelinesRepositoryInterface;

class GuidelinesResource
{
    public function __construct(
        private readonly ContentGuidelinesRepositoryInterface $repository,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpResourceTemplate(
        uriTemplate: 'sulu://guidelines/{webspace}',
        name: 'sulu_guidelines',
        description: 'Content guidelines for a webspace. Pass "global" for global defaults. Per-webspace guidelines merge with global defaults — non-null webspace values override globals.',
        mimeType: 'application/json',
    )]
    public function getGuidelines(string $webspace): array
    {
        $emptyDefaults = ['webspace' => null, 'tone' => null, 'audience' => null, 'style' => null, 'brand_rules' => null, 'dos' => null, "don'ts" => null];

        $global = $this->repository->findOneBy(['webspace' => null]);
        $resolved = $global?->toArray() ?? $emptyDefaults;

        if ('global' === $webspace) {
            return $resolved;
        }

        $specific = $this->repository->findOneBy(['webspace' => $webspace]);
        if ($specific instanceof ContentGuidelines) {
            foreach ($specific->toArray() as $field => $value) {
                if (null !== $value && 'webspace' !== $field) {
                    $resolved[$field] = $value;
                }
            }
        }

        return $resolved;
    }
}
