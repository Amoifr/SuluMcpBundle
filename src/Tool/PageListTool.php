<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageListTool
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_list',
        description: 'List pages in a webspace with optional filters. Returns an array of pages with their UUIDs and full content data (same structure as sulu_page_get). Use "template" to filter by template key (e.g. "default", "homepage"). Use "parentId" with a page UUID to list only direct children of that page. Results are paginated — use "page" and "limit" to control. Response includes "total" count for pagination. Each page in the results contains "uuid" and "data" with all template field values.',
    )]
    public function listPages(
        string $webspace,
        string $locale,
        ?string $template = null,
        ?string $parentId = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
            'page' => $page,
            'limit' => $limit,
        ];

        if (null !== $template) {
            $filters['templateKeys'] = [$template];
        }

        if (null !== $parentId) {
            $filters['parentId'] = $parentId;
        }

        $pages = $this->pageRepository->findBy(
            $filters,
            ['title' => 'asc'],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
        );

        $total = $this->pageRepository->countBy($filters);

        $results = [];
        foreach ($pages as $pageEntity) {
            $dimensionContent = $this->contentManager->resolve($pageEntity, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $results[] = [
                'uuid' => $pageEntity->getUuid(),
                'data' => $this->contentManager->normalize($dimensionContent),
            ];
        }

        return [
            'pages' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
