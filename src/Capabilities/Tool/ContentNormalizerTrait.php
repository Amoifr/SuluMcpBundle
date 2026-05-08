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
     * Detects which keys in normalized data are block properties (arrays of items with _id and type).
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

            // Check if first item looks like a block (has _id and type)
            $first = \reset($value);
            if (\is_array($first) && isset($first['_id'], $first['type'])) {
                $blockProps[] = $key;
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

            // For sections, count sub-blocks
            if (isset($block['blocks']) && \is_array($block['blocks'])) {
                $summary['blockCount'] = \count($block['blocks']);
            }

            $summaries[] = $summary;
        }

        return $summaries;
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
