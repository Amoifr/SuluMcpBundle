<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Resource;

use Mcp\Capability\Attribute\McpResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Entity\CompanyContext;
use Sulu\McpServerBundle\Repository\CompanyContextRepositoryInterface;
use Sulu\McpServerBundle\Resource\CompanyContextResource;

class CompanyContextResourceTest extends TestCase
{
    private CompanyContextRepositoryInterface&MockObject $repository;
    private CompanyContextResource $resource;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CompanyContextRepositoryInterface::class);
        $this->resource = new CompanyContextResource($this->repository);
    }

    public function testGetCompanyContextReturnsEntityData(): void
    {
        $entity = new CompanyContext();
        $entity->setCompanyName('Acme');
        $entity->setDescription('A global leader in innovation');
        $entity->setIndustry('Technology');
        $entity->setWebsite('https://acme.example.com');
        $entity->setKeyProducts('Widget Pro');

        $this->repository
            ->method('findOneBy')
            ->willReturn($entity);

        $result = $this->resource->getCompanyContext();

        $this->assertSame('Acme', $result['company_name']);
        $this->assertSame('A global leader in innovation', $result['description']);
        $this->assertSame('Technology', $result['industry']);
        $this->assertSame('https://acme.example.com', $result['website']);
        $this->assertSame('Widget Pro', $result['key_products']);
    }

    public function testGetCompanyContextReturnsNullFieldsWhenNotSet(): void
    {
        $this->repository
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->resource->getCompanyContext();

        $this->assertNull($result['company_name']);
        $this->assertNull($result['description']);
        $this->assertNull($result['industry']);
        $this->assertNull($result['website']);
        $this->assertNull($result['key_products']);
    }

    public function testGetCompanyContextMethodHasMcpResourceAttribute(): void
    {
        $reflection = new \ReflectionMethod(CompanyContextResource::class, 'getCompanyContext');
        $attributes = $reflection->getAttributes(McpResource::class);

        $this->assertCount(1, $attributes, 'getCompanyContext() must have exactly one #[McpResource] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu://context/company', $instance->uri);
        $this->assertSame('sulu_company_context', $instance->name);
    }
}
