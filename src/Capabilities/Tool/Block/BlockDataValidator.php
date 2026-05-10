<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool\Block;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

/**
 * Validates block field data against the block type's schema.
 *
 * Without this check, the MCP layer accepted any keys in blockData and forwarded
 * them to Sulu, where they were stored verbatim. The admin UI then read from the
 * expected template field keys and showed empty blocks, while the read-side
 * normalizer flattened bogus `{name, value}` pairs and hid the corruption.
 */
final readonly class BlockDataValidator
{
    public function __construct(
        private MetadataProviderInterface $formMetadataProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $blockData Normalized blockData (flat object form)
     *
     * @return array<string, mixed>|null Error payload, or null when valid
     */
    public function validate(
        string $contentType,
        ?string $templateKey,
        string $blockType,
        array $blockData,
    ): ?array {
        if ($error = $this->rejectNameValuePattern($blockType, $blockData)) {
            return $error;
        }

        $validKeys = $this->findBlockTypeFieldNames($contentType, $templateKey, $blockType);

        if (null === $validKeys) {
            // Block type not discoverable in metadata: skip strict validation rather
            // than blocking legitimate use of project-specific or inline block types
            // whose definitions we cannot resolve here.
            return null;
        }

        $invalidKeys = [];
        foreach (\array_keys($blockData) as $key) {
            if ('type' === $key || '_id' === $key || \str_starts_with((string) $key, 'settings')) {
                continue;
            }
            if (!\in_array((string) $key, $validKeys, true)) {
                $invalidKeys[] = (string) $key;
            }
        }

        if ([] === $invalidKeys) {
            return null;
        }

        return [
            'error' => \sprintf(
                'Unknown keys for block type "%s": %s. Valid keys: %s.',
                $blockType,
                \implode(', ', $invalidKeys),
                \implode(', ', $validKeys),
            ),
            'hint' => 'Pass blockData as a flat object whose keys are template field names, e.g. blockData={"title": "...", "description": "<p>...</p>"}. Use sulu_get_context to inspect block type field schemas.',
        ];
    }

    /**
     * Detect the `[{"name": "field", "value": "..."}]` storage-shape pattern that
     * AI clients sometimes emit. This shape is silently stored by Sulu and breaks
     * the admin UI -- give a tailored message before generic key validation.
     *
     * @param array<string, mixed> $blockData
     *
     * @return array<string, mixed>|null
     */
    private function rejectNameValuePattern(string $blockType, array $blockData): ?array
    {
        if (
            2 !== \count($blockData)
            || !\array_key_exists('name', $blockData)
            || !\array_key_exists('value', $blockData)
        ) {
            return null;
        }

        return [
            'error' => \sprintf(
                'Block data for "%s" is in Sulu\'s internal {name, value} storage shape, not the API shape. Pass {fieldName: value} directly, e.g. blockData={"%s": "..."} instead of blockData=[{"name": "%s", "value": "..."}].',
                $blockType,
                \is_string($blockData['name']) ? $blockData['name'] : 'fieldName',
                \is_string($blockData['name']) ? $blockData['name'] : 'fieldName',
            ),
            'hint' => 'Use sulu_get_context to see the block type\'s field schema.',
        ];
    }

    /**
     * Return the field names valid for $blockType inside $contentType templates,
     * or null when the block type cannot be discovered (caller should skip strict
     * checks in that case).
     *
     * @return list<string>|null
     */
    private function findBlockTypeFieldNames(string $contentType, ?string $templateKey, string $blockType): ?array
    {
        $fields = $this->findInTemplates($contentType, $templateKey, $blockType);
        if (null !== $fields) {
            return $fields;
        }

        return $this->findInGlobalBlocks($blockType);
    }

    /** @return list<string>|null */
    private function findInTemplates(string $contentType, ?string $templateKey, string $blockType): ?array
    {
        try {
            $typed = $this->formMetadataProvider->getMetadata($contentType, 'en', []);
        } catch (\Throwable) {
            return null;
        }

        if (!$typed instanceof TypedFormMetadata) {
            return null;
        }

        $forms = $typed->getForms();
        $candidates = null !== $templateKey && isset($forms[$templateKey])
            ? [$templateKey => $forms[$templateKey]]
            : $forms;

        foreach ($candidates as $form) {
            $fields = $this->scanFormForBlockType($form, $blockType);
            if (null !== $fields) {
                return $fields;
            }
        }

        return null;
    }

    /** @return list<string>|null */
    private function scanFormForBlockType(FormMetadata $form, string $blockType): ?array
    {
        foreach ($form->getItems() as $item) {
            if (!$item instanceof FieldMetadata || 'block' !== $item->getType()) {
                continue;
            }

            foreach ($item->getTypes() as $typeName => $blockForm) {
                if ($typeName === $blockType && [] !== $blockForm->getItems()) {
                    return $this->extractFieldNames($blockForm);
                }

                // Recurse into nested block definitions
                $nested = $this->scanFormForBlockType($blockForm, $blockType);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /** @return list<string>|null */
    private function findInGlobalBlocks(string $blockType): ?array
    {
        try {
            $typed = $this->formMetadataProvider->getMetadata('block', 'en', ['ignore_global_blocks' => true]);
        } catch (\Throwable) {
            return null;
        }

        if (!$typed instanceof TypedFormMetadata) {
            return null;
        }

        $form = $typed->getForms()[$blockType] ?? null;
        if (!$form instanceof FormMetadata || [] === $form->getItems()) {
            return null;
        }

        return $this->extractFieldNames($form);
    }

    /** @return list<string> */
    private function extractFieldNames(FormMetadata $form): array
    {
        $names = [];
        foreach ($form->getItems() as $item) {
            $names[] = $item->getName();
        }

        return $names;
    }
}
