<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageTreeTool
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
        name: 'sulu_page_tree',
        description: 'Get the full page tree as a nested hierarchy for a webspace. Each node contains uuid, title, url, template, and a "children" array with the same structure. Shows the complete site structure with nesting — use this to find the parentId when creating new pages, or to understand the site navigation. Root-level pages are direct children of the webspace root.',
    )]
    public function getPageTree(string $webspace, string $locale): array
    {
        $pages = $this->pageRepository->findByAsTree(
            [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
        );

        $tree = [];
        foreach ($pages as $page) {
            $tree[] = $this->buildTreeNode($page, $locale);
        }

        return [
            'webspace' => $webspace,
            'locale' => $locale,
            'tree' => $tree,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTreeNode(PageInterface $page, string $locale, int $depth = 0): array
    {
        /** @var PageDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentManager->resolve($page, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        $children = $page->getChildren();
        $childNodes = [];
        foreach ($children as $child) {
            $childNodes[] = $this->buildTreeNode($child, $locale, $depth + 1);
        }

        return [
            'uuid' => $page->getUuid(),
            'title' => $dimensionContent->getTitle(),
            'url' => $dimensionContent->getRoute()?->getSlug(),
            'templateKey' => $dimensionContent->getTemplateKey(),
            'hasChildren' => !$children->isEmpty(),
            'parentUuid' => $page->getParent()?->getUuid(),
            'depth' => $depth,
            'workflowPlace' => $dimensionContent->getWorkflowPlace(),
            'availableLocales' => $dimensionContent->getAvailableLocales(),
            'children' => $childNodes,
        ];
    }
}
