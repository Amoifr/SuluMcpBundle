<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\McpServerBundle\Capabilities\Tool\ContentNormalizerTrait;
use Sulu\McpServerBundle\Capabilities\Tool\ContentTypeResolver;

/**
 * @internal
 */
class BlockListTool
{
    use ContentNormalizerTrait;

    public function __construct(
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_block_list',
        description: 'Get paginated block content for a page, article, or snippet. Use this after sulu_page_get / sulu_article_get / sulu_snippet_get which return block summaries (index, _id, type, title). Pass the "blockProperty" name (e.g. "blocks", "homeBlocks") and paginate with "page" and "limit". To list blocks inside a parent block (nested blocks), pass parentBlockId with the _id of the parent block — blockProperty is still required to locate the top-level blocks. Returns full block content including HTML for the requested range.',
    )]
    public function listBlocks(
        string $type,
        string $uuid,
        string $locale,
        string $blockProperty,
        int $page = 1,
        int $limit = 3,
        ?string $parentBlockId = null,
    ): array {
        $entity = $this->contentTypeResolver->loadDraft($type, $uuid, $locale);

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

        if (null !== $parentBlockId) {
            $parentPath = $this->findBlockPath($normalized, $parentBlockId);

            if (null === $parentPath) {
                return [
                    'error' => \sprintf('Parent block with _id "%s" not found.', $parentBlockId),
                    'hint' => 'Use sulu_page_get, sulu_article_get, or sulu_snippet_get to see block summaries with _id values.',
                ];
            }

            /** @var list<array<string, mixed>> $topLevelBlocks */
            $topLevelBlocks = $normalized[$parentPath['property']];
            $parentBlock = $this->getBlockAtPath($topLevelBlocks, $parentPath['indices']);
            $nestedKey = $this->findNestedBlockKey($parentBlock);

            if (null === $nestedKey) {
                return ['error' => \sprintf('Block "%s" has no nested block list.', $parentBlockId)];
            }

            /** @var list<array<string, mixed>> $allBlocks */
            $allBlocks = $parentBlock[$nestedKey];
        } else {
            $allBlocks = $normalized[$blockProperty];
        }
        $total = \count($allBlocks);
        $offset = ($page - 1) * $limit;
        $slice = \array_slice($allBlocks, $offset, $limit);

        $cleaned = [];
        foreach ($slice as $block) {
            $cleaned[] = $this->removeEmpty($this->formatBlockForOutput($block));
        }

        return [
            'blocks' => $cleaned,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
