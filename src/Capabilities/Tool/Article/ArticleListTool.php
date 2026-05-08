<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Article;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class ArticleListTool
{
    private const SUMMARY_FIELDS = [
        'title', 'template', 'url', 'locale', 'stage',
        'published', 'publishedState', 'workflowPlace',
        'authored', 'author', 'created', 'changed',
        'availableLocales', 'contentLocales', 'ghostLocale',
        'shadowOn', 'shadowLocale',
        'mainWebspace',
    ];

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_article_list',
        description: 'List articles with optional filters. Returns lightweight summaries (title, template, URL, workflow state, dates) — no blocks or HTML content. Use sulu_article_get with a UUID to fetch the full content of a specific article. Use "template" to filter by template key (e.g. "blog", "default"). Results are paginated — use "page" and "limit" to control.',
    )]
    public function listArticles(
        string $locale,
        ?string $template = null,
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

        $articles = $this->articleRepository->findBy(
            $filters,
            ['title' => 'asc'],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true],
        );

        $total = $this->articleRepository->countBy($filters);

        $results = [];
        foreach ($articles as $articleEntity) {
            $dimensionContent = $this->contentManager->resolve($articleEntity, [
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
                'uuid' => $articleEntity->getUuid(),
                'data' => $summary,
            ];
        }

        return [
            'articles' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
