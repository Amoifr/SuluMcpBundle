<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

/**
 * @internal
 */
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
        description: 'Get the page tree as a nested hierarchy for a webspace. Each node contains uuid, title, url, template, and a "children" array with the same structure. Shows the site structure — use this to find the parentId when creating new pages, or to understand the site navigation. Root-level pages are direct children of the webspace root. Accepts an optional maxDepth to limit response size on deep site trees; when a node has hasChildren:true but children:[] the branch was depth-truncated — request again with a higher maxDepth or fetch that branch separately.',
    )]
    public function getPageTree(
        string $webspace,
        string $locale,
        #[Schema(description: 'Maximum nesting depth to return (0 = root pages only). Omit for the full tree. Use to limit response size on deep site trees.')]
        ?int $maxDepth = null,
    ): array {
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
            $tree[] = $this->buildTreeNode($page, $locale, 0, $maxDepth);
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
    private function buildTreeNode(PageInterface $page, string $locale, int $depth = 0, ?int $maxDepth = null): array
    {
        /** @var PageDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentManager->resolve($page, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        $children = $page->getChildren();
        $childNodes = [];

        if (null === $maxDepth || $depth < $maxDepth) {
            foreach ($children as $child) {
                $childNodes[] = $this->buildTreeNode($child, $locale, $depth + 1, $maxDepth);
            }
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
