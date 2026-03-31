<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageGetTool
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
        name: 'sulu_page_get',
        description: 'Get a single page by its UUID. Returns the full draft state including template key, title, URL, all template-specific content fields (e.g. "article" for text_editor fields), and block data. The response "data" object contains flat key-value pairs where keys match the template field names from sulu_get_context. Always call this before sulu_page_update to see the current field values.',
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

            return [
                'uuid' => $page->getUuid(),
                'webspace' => $webspace,
                'locale' => $locale,
                'data' => $normalized,
            ];
        } catch (PageNotFoundException) {
            return [
                'error' => 'Page not found: '.$uuid,
            ];
        }
    }
}
