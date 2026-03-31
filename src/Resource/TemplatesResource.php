<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Resource;

use Mcp\Capability\Attribute\McpResource;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\ItemMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

class TemplatesResource
{
    /** @var array<string, FormMetadata>|null */
    private ?array $globalBlockForms = null;

    public function __construct(
        private readonly MetadataProviderInterface $formMetadataProvider,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpResource(
        uri: 'sulu://templates',
        name: 'sulu_templates',
        description: 'Available Sulu page templates with their field schemas across all webspaces (per D-02: static URI cannot filter by webspace). Use the template key when creating or updating pages.',
        mimeType: 'application/json',
    )]
    public function getTemplates(): array
    {
        $typedMetadata = $this->formMetadataProvider->getMetadata('page', 'en', []);
        if (!$typedMetadata instanceof TypedFormMetadata) {
            return [];
        }

        $result = [];
        foreach ($typedMetadata->getForms() as $key => $formMetadata) {
            $result[$key] = $this->normalizeTemplate($formMetadata);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function normalizeTemplate(FormMetadata $form): array
    {
        $fields = [];
        foreach ($form->getItems() as $item) {
            $fields[] = $this->normalizeItem($item);
        }

        return ['key' => $form->getKey(), 'fields' => $fields];
    }

    /**
     * @param ItemMetadata $item
     *
     * @return array<string, mixed>
     */
    private function normalizeItem($item): array
    {
        $field = [
            'name' => $item->getName(),
            'type' => $item->getType(),
            'label' => $item->getLabel('en') ?? $item->getName(),
            'required' => $item instanceof FieldMetadata && $item->isRequired(),
        ];

        if ($item instanceof FieldMetadata && 'block' === $item->getType()) {
            $types = [];
            foreach ($item->getTypes() as $typeName => $blockForm) {
                $resolvedForm = $this->resolveBlockForm($typeName, $blockForm);
                $blockFields = [];
                foreach ($resolvedForm->getItems() as $blockItem) {
                    $blockFields[] = $this->normalizeItem($blockItem);
                }
                $types[$typeName] = [
                    'key' => $typeName,
                    'label' => $resolvedForm->getTitle('en'),
                    'fields' => $blockFields,
                ];
            }
            $field['types'] = $types;
        }

        return $field;
    }

    private function resolveBlockForm(string $blockTypeName, FormMetadata $blockForm): FormMetadata
    {
        if ([] !== $blockForm->getItems()) {
            return $blockForm;
        }

        $globalBlock = $this->getGlobalBlockForms()[$blockTypeName] ?? null;
        if (null !== $globalBlock) {
            return $globalBlock;
        }

        return $blockForm;
    }

    /**
     * @return array<string, FormMetadata>
     */
    private function getGlobalBlockForms(): array
    {
        if (null === $this->globalBlockForms) {
            $blockMetadata = $this->formMetadataProvider->getMetadata('block', 'en', ['ignore_global_blocks' => true]);
            $this->globalBlockForms = $blockMetadata instanceof TypedFormMetadata
                ? $blockMetadata->getForms()
                : [];
        }

        return $this->globalBlockForms;
    }
}
