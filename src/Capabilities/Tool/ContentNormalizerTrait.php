<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool;

/**
 * Strips empty/null values and unnecessary metadata from Sulu's normalized content
 * to keep MCP responses small enough for AI clients.
 */
trait ContentNormalizerTrait
{
    /**
     * Fields that are never useful for AI content operations.
     */
    private const STRIP_FIELDS = [
        'id',                           // duplicate of uuid
        'customizeWebspaceSettings',
        'additionalWebspaces',
        'excerptAudienceTargetGroups',
        'excerptSegment',
        'lastModified',
        'lastModifiedEnabled',
        'version',
        'stage',                        // always 'draft' in our context
    ];

    /**
     * SEO keys present on the normalized content. Surfaced only through the dedicated
     * sulu_*_seo_get / sulu_*_seo_update tools.
     */
    private const SEO_FIELDS = ['seo', 'seoNoIndex', 'seoNoFollow', 'seoHideInSitemap'];

    /**
     * Excerpt keys present on the normalized content. Surfaced only through the
     * dedicated sulu_*_excerpt_get / sulu_*_excerpt_update tools.
     */
    private const EXCERPT_FIELDS = ['excerpt'];

    /**
     * @param array<string, mixed> $normalized
     * @param list<string>         $blockProperties Block property names to summarize (e.g. ['blocks', 'homeBlocks'])
     *
     * @return array<string, mixed>
     */
    private function compactContent(array $normalized, array $blockProperties = []): array
    {
        // Remove fields that are never useful
        foreach (self::STRIP_FIELDS as $field) {
            unset($normalized[$field]);
        }

        // Replace block arrays with lightweight summaries
        foreach ($blockProperties as $prop) {
            if (isset($normalized[$prop]) && \is_array($normalized[$prop])) {
                $normalized[$prop] = $this->summarizeBlocks($normalized[$prop]);
            }
        }

        // Recursively remove null values, empty arrays, and empty strings
        return $this->removeEmpty($normalized);
    }

    /**
     * Detects which keys in normalized data are block properties (arrays whose items look like blocks).
     *
     * Scans every item in the list — a mix of id'd and non-id'd blocks (legacy data) still counts,
     * as long as at least one item has both `_id` and `type`.
     *
     * @param array<string, mixed> $normalized
     *
     * @return list<string>
     */
    private function detectBlockProperties(array $normalized): array
    {
        $blockProps = [];

        foreach ($normalized as $key => $value) {
            if (!\is_array($value) || [] === $value) {
                continue;
            }

            foreach ($value as $item) {
                if (\is_array($item) && isset($item['_id'], $item['type'])) {
                    $blockProps[] = $key;

                    continue 2;
                }
            }
        }

        return $blockProps;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function summarizeBlocks(array $blocks): array
    {
        $summaries = [];

        foreach ($blocks as $index => $block) {
            $summary = [
                'index' => $index,
                '_id' => $block['_id'] ?? null,
                'type' => $block['type'] ?? null,
            ];

            if (isset($block['title'])) {
                $summary['title'] = $block['title'];
            }

            // Recursively summarize any nested block lists
            foreach ($block as $key => $value) {
                if (!\is_array($value) || !\array_is_list($value) || [] === $value) {
                    continue;
                }
                if ($this->looksLikeBlockList($value)) {
                    /* @var list<array<string, mixed>> $value */
                    $summary[$key] = $this->summarizeBlocks($value);
                }
            }

            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * Find a block by _id anywhere in the block tree (recursive).
     *
     * @param array<string, mixed> $data
     *
     * @return array{property: string, indices: list<int>}|null
     */
    private function findBlockPath(array $data, string $blockId): ?array
    {
        foreach ($this->detectBlockProperties($data) as $property) {
            /** @var list<array<string, mixed>> $blocks */
            $blocks = $data[$property];
            $path = $this->searchBlockTree($blocks, $blockId, []);
            if (null !== $path) {
                return ['property' => $property, 'indices' => $path];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<int>                  $currentPath
     *
     * @return list<int>|null
     */
    private function searchBlockTree(array $blocks, string $blockId, array $currentPath): ?array
    {
        foreach ($blocks as $index => $block) {
            $path = [...$currentPath, $index];

            if (isset($block['_id']) && $block['_id'] === $blockId) {
                return $path;
            }

            // Recurse into nested block arrays — accept lists where any item looks like a block.
            foreach ($block as $value) {
                if (!\is_array($value) || !\array_is_list($value) || [] === $value) {
                    continue;
                }
                if (!$this->looksLikeBlockList($value)) {
                    continue;
                }
                /** @var list<array<string, mixed>> $value */
                $nested = $this->searchBlockTree($value, $blockId, $path);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Get the block at a path. Each index after the first navigates into the first
     * nested block list found in the parent block.
     *
     * @param list<array<string, mixed>> $blocks
     * @param list<int>                  $indices
     *
     * @return array<string, mixed>
     */
    private function getBlockAtPath(array $blocks, array $indices): array
    {
        $block = $blocks[$indices[0]];

        foreach (\array_slice($indices, 1) as $index) {
            $nestedKey = $this->findNestedBlockKey($block);
            if (null === $nestedKey) {
                return [];
            }
            /** @var list<array<string, mixed>> $nestedBlocks */
            $nestedBlocks = $block[$nestedKey];
            $block = $nestedBlocks[$index];
        }

        return $block;
    }

    /**
     * Return a new blocks array with the block at $indices replaced/merged with $updated.
     *
     * @param list<array<string, mixed>> $blocks
     * @param list<int>                  $indices
     * @param array<string, mixed>       $updated
     *
     * @return list<array<string, mixed>>
     */
    private function setBlockAtPath(array $blocks, array $indices, array $updated): array
    {
        $firstIndex = $indices[0];
        $rest = \array_slice($indices, 1);

        if ([] === $rest) {
            $blocks[$firstIndex] = \array_merge($blocks[$firstIndex], $updated);

            return $blocks;
        }

        $nestedKey = $this->findNestedBlockKey($blocks[$firstIndex]);
        if (null === $nestedKey) {
            return $blocks;
        }

        /** @var list<array<string, mixed>> $nestedBlocks */
        $nestedBlocks = $blocks[$firstIndex][$nestedKey];
        $blocks[$firstIndex][$nestedKey] = $this->setBlockAtPath($nestedBlocks, $rest, $updated);

        return $blocks;
    }

    /**
     * Insert a block into the nested block list of a parent block at the given path.
     *
     * @param list<array<string, mixed>> $blocks
     * @param list<int>                  $parentIndices
     * @param array<string, mixed>       $newBlock
     *
     * @return array{blocks: list<array<string, mixed>>, nestedKey: string, addedAt: int}|null
     */
    private function insertBlockAtPath(array $blocks, array $parentIndices, array $newBlock, ?int $position): ?array
    {
        $parent = $this->getBlockAtPath($blocks, $parentIndices);
        $nestedKey = $this->findNestedBlockKey($parent);

        if (null === $nestedKey) {
            // Parent has no nested block list — use first block-like key or default to 'blocks'
            $nestedKey = 'blocks';
        }

        /** @var list<array<string, mixed>> $nestedBlocks */
        $nestedBlocks = $parent[$nestedKey] ?? [];

        if (null !== $position && $position >= 0 && $position <= \count($nestedBlocks)) {
            \array_splice($nestedBlocks, $position, 0, [$newBlock]);
            $addedAt = $position;
        } else {
            $nestedBlocks[] = $newBlock;
            $addedAt = \count($nestedBlocks) - 1;
        }

        $updatedParent = \array_merge($parent, [$nestedKey => $nestedBlocks]);
        $firstIndex = $parentIndices[0];
        $rest = \array_slice($parentIndices, 1);

        if ([] === $rest) {
            $blocks[$firstIndex] = $updatedParent;
        } else {
            $outerNestedKey = $this->findNestedBlockKey($blocks[$firstIndex]);
            if (null !== $outerNestedKey) {
                /** @var list<array<string, mixed>> $outerNested */
                $outerNested = $blocks[$firstIndex][$outerNestedKey];
                $blocks[$firstIndex][$outerNestedKey] = $this->setBlockAtPath($outerNested, $rest, $updatedParent);
            }
        }

        return ['blocks' => $blocks, 'nestedKey' => $nestedKey, 'addedAt' => $addedAt];
    }

    /**
     * Find the first key in a block that holds a nested block list.
     *
     * @param array<string, mixed> $block
     */
    private function findNestedBlockKey(array $block): ?string
    {
        foreach ($block as $key => $value) {
            if (!\is_array($value) || !\array_is_list($value) || [] === $value) {
                continue;
            }
            if ($this->looksLikeBlockList($value)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $list
     */
    private function looksLikeBlockList(array $list): bool
    {
        foreach ($list as $item) {
            if (\is_array($item) && isset($item['_id'], $item['type'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a raw Sulu-normalized block into a clean flat map for AI clients.
     *
     * Sulu normalizes block properties as {name, value} pairs in two layouts:
     *   - Single property: the pair is merged directly into the block
     *     → {type:"heading", name:"title", value:"Hello"}
     *   - Multiple properties: each pair sits at a numeric index
     *     → {type:"quote", 0:{name:"text",value:"…"}, 1:{name:"attribution",value:"…"}}
     *
     * Both are flattened to {_id?, type, title:"Hello"} / {_id?, type, text:"…", attribution:"…"}.
     *
     * @param array<int|string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function formatBlockForOutput(array $block): array
    {
        $out = [];

        if (isset($block['_id'])) {
            $out['_id'] = $block['_id'];
        }

        $out['type'] = $block['type'] ?? null;

        // Single-property layout: block itself carries "name" + "value"
        if (isset($block['name'], $block['value'])) {
            $out[(string) $block['name']] = $block['value'];

            return $out;
        }

        // Walk remaining keys: copy named string keys, expand numeric {name,value} entries
        foreach ($block as $key => $value) {
            if (\in_array($key, ['_id', 'type'], true)) {
                continue;
            }

            if (\is_int($key) && \is_array($value) && isset($value['name'])) {
                // Multi-property layout entry
                $out[(string) $value['name']] = $value['value'] ?? null;
            } elseif (\is_string($key)) {
                // Already a named field (e.g. nested block list or a flat string property)
                if (\is_array($value) && \array_is_list($value) && [] !== $value && $this->looksLikeBlockList($value)) {
                    /* @var list<array<int|string, mixed>> $value */
                    $out[$key] = \array_map(fn (array $b) => $this->formatBlockForOutput($b), $value);
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * Remove SEO and excerpt keys from normalized content. Used by the generic
     * page/article get tools so SEO/excerpt are only surfaced through the dedicated tools.
     *
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function stripSeoExcerpt(array $normalized): array
    {
        foreach ([...self::SEO_FIELDS, ...self::EXCERPT_FIELDS] as $field) {
            unset($normalized[$field]);
        }

        return $normalized;
    }

    /**
     * Pluck SEO fields from normalized content.
     *
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function extractSeo(array $normalized): array
    {
        $out = [];
        foreach (self::SEO_FIELDS as $field) {
            if (\array_key_exists($field, $normalized)) {
                $out[$field] = $normalized[$field];
            }
        }

        return $out;
    }

    /**
     * Pluck excerpt fields from normalized content.
     *
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function extractExcerpt(array $normalized): array
    {
        return ['excerpt' => $normalized['excerpt'] ?? []];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function removeEmpty(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (null === $value || [] === $value || '' === $value) {
                continue;
            }

            if (\is_array($value)) {
                $cleaned = $this->removeEmpty($value);
                if ([] !== $cleaned) {
                    $result[$key] = $cleaned;
                }

                continue;
            }

            // Keep false and 0 — they carry meaning
            $result[$key] = $value;
        }

        return $result;
    }
}
