<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Snippet;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetListTool
{
    public function __construct(
        private readonly SnippetRepositoryInterface $snippetRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_snippet_list',
        description: 'List snippets with optional template filter. Snippets are global reusable content. Returns paginated list with total count.',
    )]
    public function listSnippets(string $locale, ?string $template = null, int $page = 1, int $limit = 20): array
    {
        try {
            $filters = [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'page' => $page,
                'limit' => $limit,
            ];

            if (null !== $template) {
                $filters['templateKeys'] = [$template];
            }

            $selects = [
                SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_ADMIN => true,
            ];

            $snippets = $this->snippetRepository->findBy($filters, [], $selects);
            $total = $this->snippetRepository->countBy($filters);

            $results = [];
            foreach ($snippets as $snippet) {
                $dimensionContent = $this->contentManager->resolve($snippet, [
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ]);

                $normalized = $this->contentManager->normalize($dimensionContent);
                $results[] = [
                    'uuid' => $snippet->getUuid(),
                    'data' => $normalized,
                ];
            }

            return [
                'snippets' => $results,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list snippets: %s', $e->getMessage()),
            ];
        }
    }
}
