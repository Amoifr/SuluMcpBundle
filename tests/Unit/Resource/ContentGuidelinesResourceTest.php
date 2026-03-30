<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Entity\ContentGuidelines;
use Sulu\McpServerBundle\Repository\ContentGuidelinesRepositoryInterface;
use Sulu\McpServerBundle\Resource\GuidelinesResource;

class ContentGuidelinesResourceTest extends TestCase
{
    private ContentGuidelinesRepositoryInterface&MockObject $repository;
    private GuidelinesResource $resource;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ContentGuidelinesRepositoryInterface::class);
        $this->resource = new GuidelinesResource($this->repository);
    }

    public function testGetGuidelinesCallsFindOneByForGlobalAndSpecific(): void
    {
        $this->repository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function (array $filters): ?ContentGuidelines {
                if (['webspace' => null] === $filters) {
                    return null; // no global guidelines
                }
                if (['webspace' => 'website'] === $filters) {
                    return null; // no specific guidelines
                }

                return null;
            });

        $this->resource->getGuidelines('website');
    }

    public function testGetGuidelinesReturnsMergedArray(): void
    {
        $global = new ContentGuidelines();
        $global->setTone('professional');
        $global->setAudience('enterprise');

        $specific = new ContentGuidelines();
        $specific->setWebspace('website');
        $specific->setTone('friendly'); // overrides global

        $this->repository
            ->method('findOneBy')
            ->willReturnCallback(function (array $filters) use ($global, $specific): ?ContentGuidelines {
                if (['webspace' => null] === $filters) {
                    return $global;
                }
                if (['webspace' => 'website'] === $filters) {
                    return $specific;
                }

                return null;
            });

        $result = $this->resource->getGuidelines('website');

        $this->assertSame('friendly', $result['tone']); // specific overrides global
        $this->assertSame('enterprise', $result['audience']); // global falls through
    }

    public function testGetGuidelinesHandlesGlobalWebspace(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['webspace' => null])
            ->willReturn(null);

        $result = $this->resource->getGuidelines('global');

        $this->assertNull($result['tone']);
        $this->assertNull($result['audience']);
    }

    public function testGetGuidelinesMethodHasMcpResourceTemplateAttribute(): void
    {
        $reflection = new \ReflectionMethod(GuidelinesResource::class, 'getGuidelines');
        $attributes = $reflection->getAttributes(McpResourceTemplate::class);

        $this->assertCount(1, $attributes, 'getGuidelines() must have exactly one #[McpResourceTemplate] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu://guidelines/{webspace}', $instance->uriTemplate);
        $this->assertSame('sulu_guidelines', $instance->name);
    }
}
