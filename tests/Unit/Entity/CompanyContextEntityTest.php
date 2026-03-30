<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Entity\CompanyContext;

class CompanyContextEntityTest extends TestCase
{
    public function testEntityHasCorrectTableName(): void
    {
        $reflection = new \ReflectionClass(CompanyContext::class);
        $attributes = $reflection->getAttributes(ORM\Table::class);

        $this->assertCount(1, $attributes, 'CompanyContext must have exactly one #[ORM\Table] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_mcp_company_context', $instance->name);
    }

    public function testAllFieldsAreNullable(): void
    {
        $reflection = new \ReflectionClass(CompanyContext::class);
        $nullableFields = ['companyName', 'description', 'industry', 'website', 'keyProducts'];

        foreach ($nullableFields as $fieldName) {
            $property = $reflection->getProperty($fieldName);
            $columnAttributes = $property->getAttributes(ORM\Column::class);

            $this->assertCount(1, $columnAttributes, \sprintf('%s must have #[ORM\Column] attribute', $fieldName));

            $instance = $columnAttributes[0]->newInstance();
            $this->assertTrue($instance->nullable, \sprintf('%s column must be nullable', $fieldName));
        }
    }

    public function testSettersAndGetters(): void
    {
        $entity = new CompanyContext();

        $entity->setCompanyName('Acme');
        $this->assertSame('Acme', $entity->getCompanyName());

        $entity->setDescription('A global leader in innovation');
        $this->assertSame('A global leader in innovation', $entity->getDescription());

        $entity->setIndustry('Technology');
        $this->assertSame('Technology', $entity->getIndustry());

        $entity->setWebsite('https://acme.example.com');
        $this->assertSame('https://acme.example.com', $entity->getWebsite());

        $entity->setKeyProducts('Widget Pro, Widget Lite');
        $this->assertSame('Widget Pro, Widget Lite', $entity->getKeyProducts());
    }
}
