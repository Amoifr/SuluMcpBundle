<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageSeoGetTool
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
        name: 'sulu_page_seo_get',
        description: 'Read the SEO metadata for a page (seo: {title, description, keywords, canonicalUrl} plus seoNoIndex/seoNoFollow/seoHideInSitemap flags). Returns the draft state. Use sulu_page_seo_update to write.',
    )]
    public function getPageSeo(string $uuid, string $locale): array
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

            return \array_merge(
                [
                    'uuid' => $page->getUuid(),
                    'locale' => $locale,
                ],
                $this->extractSeo($normalized),
            );
        } catch (PageNotFoundException) {
            return [
                'error' => 'Page not found: '.$uuid,
            ];
        }
    }
}
