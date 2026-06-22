<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

/**
 * @internal
 */
class PageGetTool
{
    use ContentNormalizerTrait;

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_get',
        description: 'Get a single page by its UUID. Returns draft metadata, template fields, block summaries (index, _id, type, title), and SEO/excerpt data. Use sulu_block_list with type="page" to fetch full block content. Always call this before sulu_page_update.',
    )]
    public function getPage(string $webspace, string $locale, string $uuid): array
    {
        try {
            $page = $this->pageRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            );

            $dimensionContent = $this->contentManager->resolve($page, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $normalized = $this->contentManager->normalize($dimensionContent);

            $compacted = $this->compactContent($normalized, $this->detectBlockProperties($normalized));

            return [
                'uuid' => $page->getUuid(),
                'webspace' => $webspace,
                'locale' => $locale,
                'data' => $compacted,
            ];
        } catch (PageNotFoundException) {
            return [
                'error' => 'Page not found: '.$uuid,
                'hint' => 'Verify the UUID and locale. Use sulu_page_list or sulu_content_search to find pages.',
            ];
        }
    }
}
