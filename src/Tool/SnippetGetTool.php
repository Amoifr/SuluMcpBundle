<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Exception\SnippetNotFoundException;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetGetTool
{
    use ContentNormalizerTrait;

    public function __construct(
        private readonly SnippetRepositoryInterface $snippetRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_snippet_get',
        description: 'Get a snippet by UUID. Snippets are reusable content blocks (e.g., contact info, footer content) shared across pages. Returns full content data. Snippets are global — not scoped to a webspace.',
    )]
    public function getSnippet(string $locale, string $uuid): array
    {
        try {
            $snippet = $this->snippetRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_ADMIN => true,
                ],
            );

            $dimensionContent = $this->contentManager->resolve($snippet, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $normalized = $this->contentManager->normalize($dimensionContent);

            return [
                'uuid' => $snippet->getUuid(),
                'locale' => $locale,
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];
        } catch (SnippetNotFoundException) {
            return [
                'error' => 'Snippet not found: '.$uuid,
            ];
        }
    }
}
