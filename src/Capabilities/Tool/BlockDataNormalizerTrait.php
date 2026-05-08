<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool;

/**
 * Normalizes block data from AI clients and ensures string keys for Sulu compatibility.
 *
 * AI clients (Claude, ChatGPT) sometimes send block data as a list [{"key": "value"}]
 * instead of a flat object {"key": "value"}. This trait provides normalization methods.
 */
trait BlockDataNormalizerTrait
{
    /**
     * Normalize blockData from AI clients that may send it as a list.
     *
     * Handles: [{"content": "..."}] -> {"content": "..."}
     * Passes through: {"content": "..."} -> {"content": "..."}
     *
     * Also ensures all keys are strings (Sulu's MetadataResolver requires string keys).
     *
     * @param array<mixed> $blockData
     *
     * @return array<string, mixed>
     */
    private function normalizeBlockData(array $blockData): array
    {
        // If it's a list with one element, extract that element
        if (\array_is_list($blockData) && 1 === \count($blockData) && \is_array($blockData[0])) {
            $blockData = $blockData[0];
        }

        // Ensure all keys are strings (Sulu's MetadataResolver requires string keys)
        return $this->stringifyKeys($blockData);
    }

    /**
     * Recursively convert all array keys to strings.
     * Sulu's MetadataResolver requires string keys (it uses str_contains() on keys).
     *
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    private function stringifyKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $stringKey = (string) $key;
            $result[$stringKey] = \is_array($value) ? $this->stringifyKeys($value) : $value;
        }

        return $result;
    }
}
