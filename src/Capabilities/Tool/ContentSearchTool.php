<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Search\Condition\Condition;
use Mcp\Capability\Attribute\McpTool;

class ContentSearchTool
{
    public function __construct(
        private readonly EngineInterface $engine,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_content_search',
        description: 'Search published website content (articles and pages) by keyword. Searches both titles and full content text. Returns matching items with their UUID and resource type — use resourceKey to pick the right get tool (sulu_article_get or sulu_page_get) and resourceId as the UUID. Filter by webspace to scope results to one site. Only published content is searchable.',
    )]
    public function search(
        string $query,
        string $locale,
        ?string $webspace = null,
        ?string $type = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        $builder = $this->engine->createSearchBuilder('website')
            ->addFilter(Condition::search($query))
            ->addFilter(Condition::equal('locale', $locale))
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        if (null !== $webspace) {
            $builder->addFilter(Condition::equal('webspaces', $webspace));
        }

        if (null !== $type) {
            $builder->addFilter(Condition::equal('resourceKey', $type));
        }

        $result = $builder->getResult();

        $results = [];
        foreach ($result as $document) {
            $results[] = [
                'resourceKey' => $document['resourceKey'] ?? null,
                'resourceId' => $document['resourceId'] ?? null,
                'locale' => $document['locale'] ?? null,
                'title' => $document['title'] ?? null,
                'url' => $document['url'] ?? null,
                'webspaces' => $document['webspaces'] ?? [],
                'authoredAt' => $document['authoredAt'] ?? null,
                'metadata' => $document['metadata'] ?? [],
            ];
        }

        return [
            'results' => $results,
            'total' => $result->total(),
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
