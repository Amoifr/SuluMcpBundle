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

    public function testGetTemplatesReturnsTemplatesGroupedByContentType(): void
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
            ->willReturnCallback(fn (string $key) => 'page' === $key ? $typedMetadata : null);

        $result = $this->resource->getTemplates();

        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('default', $result['page']);
        $this->assertArrayHasKey('fields', $result['page']['default']);
        $this->assertIsArray($result['page']['default']['fields']);
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
            ->willReturnCallback(fn (string $key) => 'page' === $key ? $typedMetadata : null);

        $result = $this->resource->getTemplates();

        $fields = $result['page']['default']['fields'];
        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('name', $fields[0]);
        $this->assertArrayHasKey('type', $fields[0]);
        $this->assertArrayHasKey('label', $fields[0]);
        $this->assertArrayHasKey('required', $fields[0]);
        $this->assertSame('title', $fields[0]['name']);
        $this->assertSame('text_line', $fields[0]['type']);
    }

    public function testGetTemplatesGroupsPageArticleAndSnippet(): void
    {
        $buildTyped = function (string $templateKey, string $fieldName): TypedFormMetadata {
            $field = new FieldMetadata($fieldName);
            $field->setType('text_line');
            $form = new FormMetadata();
            $form->setKey($templateKey);
            $form->addItem($field);
            $typed = new TypedFormMetadata();
            $typed->addForm($templateKey, $form);

            return $typed;
        };

        $pageMetadata = $buildTyped('default', 'title');
        $articleMetadata = $buildTyped('blog', 'headline');
        $snippetMetadata = $buildTyped('teaser', 'label');

        $this->formMetadataProvider
            ->method('getMetadata')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'page' => $pageMetadata,
                'article' => $articleMetadata,
                'snippet' => $snippetMetadata,
                default => null,
            });

        $result = $this->resource->getTemplates();

        $this->assertSame(['page', 'article', 'snippet'], array_keys($result));
        $this->assertArrayHasKey('default', $result['page']);
        $this->assertArrayHasKey('blog', $result['article']);
        $this->assertArrayHasKey('teaser', $result['snippet']);
        $this->assertSame('headline', $result['article']['blog']['fields'][0]['name']);
    }

    public function testGetTemplatesOmitsContentTypesWithoutMetadata(): void
    {
        $field = new FieldMetadata('title');
        $field->setType('text_line');
        $form = new FormMetadata();
        $form->setKey('default');
        $form->addItem($field);
        $pageMetadata = new TypedFormMetadata();
        $pageMetadata->addForm('default', $form);

        $this->formMetadataProvider
            ->method('getMetadata')
            ->willReturnCallback(fn (string $key) => match ($key) {
                'page' => $pageMetadata,
                'article' => throw new \RuntimeException('Article metadata not installed'),
                default => null,
            });

        $result = $this->resource->getTemplates();

        $this->assertSame(['page'], array_keys($result));
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

    public function testGetTemplatesResourceDescriptionMentionsGrouping(): void
    {
        $reflection = new \ReflectionMethod(TemplatesResource::class, 'getTemplates');
        $attribute = $reflection->getAttributes(McpResource::class)[0]->newInstance();

        $this->assertStringContainsString('page', $attribute->description);
        $this->assertStringContainsString('article', $attribute->description);
        $this->assertStringContainsString('snippet', $attribute->description);
    }
}
