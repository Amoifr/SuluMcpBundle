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
                $blockFields = [];
                foreach ($blockForm->getItems() as $blockItem) {
                    $blockFields[] = $this->normalizeItem($blockItem);
                }
                $types[$typeName] = [
                    'key' => $typeName,
                    'label' => $blockForm->getTitle('en'),
                    'fields' => $blockFields,
                ];
            }
            $field['types'] = $types;
        }

        return $field;
    }
}
