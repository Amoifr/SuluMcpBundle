<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class BlockListTool
{
    use ContentNormalizerTrait;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly SnippetRepositoryInterface $snippetRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_block_list',
        description: 'Get paginated block content for a page, article, or snippet. Use this after sulu_page_get / sulu_article_get which return block summaries (index, _id, type, title). Pass the "blockProperty" name (e.g. "blocks", "homeBlocks") and paginate with "page" and "limit". Returns full block content including HTML for the requested range.',
    )]
    public function listBlocks(
        string $type,
        string $uuid,
        string $locale,
        string $blockProperty,
        int $page = 1,
        int $limit = 3,
    ): array {
        $entity = $this->loadEntity($type, $uuid, $locale);

        if (null === $entity) {
            return ['error' => \sprintf('%s not found: %s', \ucfirst($type), $uuid)];
        }

        $dimensionContent = $this->contentManager->resolve($entity, [ // @phpstan-ignore argument.templateType
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        $normalized = $this->contentManager->normalize($dimensionContent);

        if (!isset($normalized[$blockProperty]) || !\is_array($normalized[$blockProperty])) {
            return ['error' => \sprintf('Block property "%s" not found. Available: %s', $blockProperty, \implode(', ', $this->detectBlockProperties($normalized)))];
        }

        $allBlocks = $normalized[$blockProperty];
        $total = \count($allBlocks);
        $offset = ($page - 1) * $limit;
        $slice = \array_slice($allBlocks, $offset, $limit);

        // Clean each block individually
        $cleaned = [];
        foreach ($slice as $index => $block) {
            $cleaned[] = $this->removeEmpty($block);
        }

        return [
            'blocks' => $cleaned,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    private function loadEntity(string $type, string $uuid, string $locale): ?object
    {
        $filters = [
            'uuid' => $uuid,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        try {
            return match ($type) {
                'page' => $this->pageRepository->getOneBy($filters, [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true]),
                'article' => $this->articleRepository->getOneBy($filters, [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true]),
                'snippet' => $this->snippetRepository->getOneBy($filters, [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_ADMIN => true]),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
