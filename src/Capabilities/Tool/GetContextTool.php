<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capabilities\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Capabilities\Resource\BlocksResource;
use Sulu\McpServerBundle\Capabilities\Resource\ExtensionFieldsResource;
use Sulu\McpServerBundle\Capabilities\Resource\FieldValueExampleProvider;
use Sulu\McpServerBundle\Capabilities\Resource\TemplatesResource;
use Sulu\McpServerBundle\Capabilities\Resource\WebspacesResource;

/**
 * @internal
 */
class GetContextTool
{
    public function __construct(
        private readonly TemplatesResource $templatesResource,
        private readonly BlocksResource $blocksResource,
        private readonly WebspacesResource $webspacesResource,
        private readonly FieldValueExampleProvider $valueExampleProvider,
        private readonly ExtensionFieldsResource $extensionFieldsResource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_get_context',
        description: 'Aggregates all CMS context into a single response. Returns templates (grouped by content type: `page`, `article`, `snippet`), block types, webspaces, and a `fieldTypes` legend mapping each field type to one value example/hint (look up a field\'s `type` in the legend to learn how to fill it). Call this once before creating or editing content to get full CMS awareness — including which article templates are available and which URL routing form each template expects (look at the field with type `route` or `page_tree_route`). Also returns `seoFields` and `excerptFields`: the project\'s configured SEO and excerpt field lists (with name, type, label, and required) to use when passing `seo` or `excerpt` data to create/update tools.',
    )]
    public function getContext(): array
    {
        $templates = $this->templatesResource->getTemplates();
        $blocks = $this->blocksResource->getBlocks();
        $webspaces = $this->webspacesResource->getWebspaces();
        $extensionFields = $this->extensionFieldsResource->getExtensionFields();

        return [
            'templates' => $templates,
            'blocks' => $blocks,
            'webspaces' => $webspaces,
            'seoFields' => $extensionFields['seo'],
            'excerptFields' => $extensionFields['excerpt'],
            'fieldTypes' => $this->buildFieldTypeLegend([$templates, $blocks]),
        ];
    }

    /**
     * Builds a single value example/hint per field type present in the payload, so the
     * examples are listed once in a legend instead of repeated on every field.
     *
     * @param array<mixed> $structures
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildFieldTypeLegend(array $structures): array
    {
        $presentTypes = [];
        $this->collectTypes($structures, $presentTypes);

        $legend = [];
        foreach (array_keys($presentTypes) as $type) {
            $valueInfo = $this->valueExampleProvider->describe($type);
            if (null === $valueInfo) {
                continue;
            }

            $entry = ['example' => $valueInfo['example']];
            if (null !== $valueInfo['hint']) {
                $entry['hint'] = $valueInfo['hint'];
            }
            $legend[$type] = $entry;
        }

        \ksort($legend);

        return $legend;
    }

    /**
     * @param array<string, true> $types
     */
    private function collectTypes(mixed $node, array &$types): void
    {
        if (!\is_array($node)) {
            return;
        }

        if (isset($node['type']) && \is_string($node['type'])) {
            $types[$node['type']] = true;
        }

        foreach ($node as $value) {
            $this->collectTypes($value, $types);
        }
    }
}
