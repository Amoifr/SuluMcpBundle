<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Resource;

use Mcp\Capability\Attribute\McpResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\McpServerBundle\Capabilities\Resource\TemplatesResource;

class TemplateResourceTest extends TestCase
{
    private MetadataProviderInterface&MockObject $formMetadataProvider;
    private TemplatesResource $resource;

    protected function setUp(): void
    {
        $this->formMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        $this->resource = new TemplatesResource($this->formMetadataProvider);
    }

    public function testGetTemplatesReturnsArrayIndexedByTemplateKey(): void
    {
        $field = new FieldMetadata('title');
        $field->setType('text_line');

        $form = new FormMetadata();
        $form->setKey('default');
        $form->addItem($field);

        $typedMetadata = new TypedFormMetadata();
        $typedMetadata->addForm('default', $form);

        $this->formMetadataProvider
            ->method('getMetadata')
            ->willReturn($typedMetadata);

        $result = $this->resource->getTemplates();

        $this->assertArrayHasKey('default', $result);
        $this->assertArrayHasKey('fields', $result['default']);
        $this->assertIsArray($result['default']['fields']);
    }

    public function testGetTemplatesFieldIncludesNameTypeLabel(): void
    {
        $field = new FieldMetadata('title');
        $field->setType('text_line');
        $field->setLabel('Title', 'en');

        $form = new FormMetadata();
        $form->setKey('default');
        $form->addItem($field);

        $typedMetadata = new TypedFormMetadata();
        $typedMetadata->addForm('default', $form);

        $this->formMetadataProvider
            ->method('getMetadata')
            ->willReturn($typedMetadata);

        $result = $this->resource->getTemplates();

        $fields = $result['default']['fields'];
        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('name', $fields[0]);
        $this->assertArrayHasKey('type', $fields[0]);
        $this->assertArrayHasKey('label', $fields[0]);
        $this->assertArrayHasKey('required', $fields[0]);
        $this->assertSame('title', $fields[0]['name']);
        $this->assertSame('text_line', $fields[0]['type']);
    }

    public function testGetTemplatesMethodHasMcpResourceAttribute(): void
    {
        $reflection = new \ReflectionMethod(TemplatesResource::class, 'getTemplates');
        $attributes = $reflection->getAttributes(McpResource::class);

        $this->assertCount(1, $attributes, 'getTemplates() method must have exactly one #[McpResource] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu://templates', $instance->uri);
        $this->assertSame('sulu_templates', $instance->name);
    }

    public function testGetTemplatesReturnsEmptyArrayWhenProviderReturnsNonTypedFormMetadata(): void
    {
        $nonTypedMetadata = $this->createMock(MetadataInterface::class);

        $this->formMetadataProvider
            ->method('getMetadata')
            ->willReturn($nonTypedMetadata);

        $result = $this->resource->getTemplates();

        $this->assertSame([], $result);
    }
}
