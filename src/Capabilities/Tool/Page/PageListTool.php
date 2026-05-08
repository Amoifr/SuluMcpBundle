<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageListTool
{
    private const SUMMARY_FIELDS = [
        'title', 'template', 'url', 'locale', 'stage',
        'published', 'publishedState', 'workflowPlace',
        'authored', 'author', 'created', 'changed',
        'availableLocales', 'contentLocales', 'ghostLocale',
        'shadowOn', 'shadowLocale',
        'navigationContexts',
    ];

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
        description: 'List pages in a webspace with optional filters. Returns lightweight summaries (title, template, URL, workflow state, dates) — no blocks or HTML content. Use sulu_page_get with a UUID to fetch the full content of a specific page. Use "template" to filter by template key (e.g. "default", "homepage"). Use "parentId" with a page UUID to list only direct children. Results are paginated — use "page" and "limit" to control.',
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

            $normalized = $this->contentManager->normalize($dimensionContent);

            $summary = [];
            foreach (self::SUMMARY_FIELDS as $field) {
                if (\array_key_exists($field, $normalized)) {
                    $summary[$field] = $normalized[$field];
                }
            }

            $results[] = [
                'uuid' => $pageEntity->getUuid(),
                'data' => $summary,
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
