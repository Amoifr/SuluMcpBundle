<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Entity\ContentGuidelines;

class ContentGuidelinesEntityTest extends TestCase
{
    public function testEntityHasCorrectTableName(): void
    {
        $reflection = new \ReflectionClass(ContentGuidelines::class);
        $attributes = $reflection->getAttributes(ORM\Table::class);

        $this->assertCount(1, $attributes, 'ContentGuidelines must have exactly one #[ORM\Table] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_mcp_content_guidelines', $instance->name);
    }

    public function testWebspaceNullableAndUnique(): void
    {
        $reflection = new \ReflectionClass(ContentGuidelines::class);
        $property = $reflection->getProperty('webspace');
        $columnAttributes = $property->getAttributes(ORM\Column::class);

        $this->assertCount(1, $columnAttributes, 'webspace property must have #[ORM\Column] attribute');

        $instance = $columnAttributes[0]->newInstance();
        $this->assertTrue($instance->nullable, 'webspace column must be nullable');

        // Unique constraint is defined at class level
        $uniqueAttributes = $reflection->getAttributes(ORM\UniqueConstraint::class);
        $this->assertCount(1, $uniqueAttributes, 'ContentGuidelines must have a unique constraint');
    }

    public function testAllTextFieldsAreNullable(): void
    {
        $reflection = new \ReflectionClass(ContentGuidelines::class);
        $nullableFields = ['tone', 'audience', 'style', 'brandRules', 'dos', 'donts'];

        foreach ($nullableFields as $fieldName) {
            $property = $reflection->getProperty($fieldName);
            $columnAttributes = $property->getAttributes(ORM\Column::class);

            $this->assertCount(1, $columnAttributes, \sprintf('%s must have #[ORM\Column] attribute', $fieldName));

            $instance = $columnAttributes[0]->newInstance();
            $this->assertTrue($instance->nullable, \sprintf('%s column must be nullable', $fieldName));
            $this->assertSame('text', $instance->type, \sprintf('%s column must be type text', $fieldName));
        }
    }

    public function testSettersAndGetters(): void
    {
        $entity = new ContentGuidelines();

        $entity->setTone('friendly');
        $this->assertSame('friendly', $entity->getTone());

        $entity->setAudience('developers');
        $this->assertSame('developers', $entity->getAudience());

        $entity->setStyle('concise');
        $this->assertSame('concise', $entity->getStyle());

        $entity->setBrandRules('Always use our brand voice');
        $this->assertSame('Always use our brand voice', $entity->getBrandRules());

        $entity->setDos('Use active voice');
        $this->assertSame('Use active voice', $entity->getDos());

        $entity->setDonts('Avoid jargon');
        $this->assertSame('Avoid jargon', $entity->getDonts());

        $entity->setWebspace('example-website');
        $this->assertSame('example-website', $entity->getWebspace());
    }
}
