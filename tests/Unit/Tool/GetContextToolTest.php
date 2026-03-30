<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Resource\BlocksResource;
use Sulu\McpServerBundle\Resource\CompanyContextResource;
use Sulu\McpServerBundle\Resource\GuidelinesResource;
use Sulu\McpServerBundle\Resource\TemplatesResource;
use Sulu\McpServerBundle\Resource\WebspacesResource;
use Sulu\McpServerBundle\Tool\GetContextTool;

class GetContextToolTest extends TestCase
{
    private TemplatesResource&MockObject $templatesResource;
    private BlocksResource&MockObject $blocksResource;
    private WebspacesResource&MockObject $webspacesResource;
    private GuidelinesResource&MockObject $guidelinesResource;
    private CompanyContextResource&MockObject $companyContextResource;
    private GetContextTool $tool;

    protected function setUp(): void
    {
        $this->templatesResource = $this->createMock(TemplatesResource::class);
        $this->blocksResource = $this->createMock(BlocksResource::class);
        $this->webspacesResource = $this->createMock(WebspacesResource::class);
        $this->guidelinesResource = $this->createMock(GuidelinesResource::class);
        $this->companyContextResource = $this->createMock(CompanyContextResource::class);

        $this->tool = new GetContextTool(
            $this->templatesResource,
            $this->blocksResource,
            $this->webspacesResource,
            $this->guidelinesResource,
            $this->companyContextResource,
        );
    }

    public function testGetContextReturnsAllKeys(): void
    {
        $this->templatesResource->method('getTemplates')->willReturn(['default' => ['title' => 'Default']]);
        $this->blocksResource->method('getBlocks')->willReturn([['type' => 'text']]);
        $this->webspacesResource->method('getWebspaces')->willReturn([['key' => 'example']]);
        $this->guidelinesResource->method('getGuidelines')->willReturn(['tone' => 'formal']);
        $this->companyContextResource->method('getCompanyContext')->willReturn(['name' => 'Acme']);

        $result = $this->tool->getContext('example');

        $this->assertArrayHasKey('templates', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('webspaces', $result);
        $this->assertArrayHasKey('guidelines', $result);
        $this->assertArrayHasKey('company_context', $result);
    }

    public function testGetContextDelegatesToAllFiveResources(): void
    {
        $this->templatesResource->expects($this->once())->method('getTemplates')->willReturn([]);
        $this->blocksResource->expects($this->once())->method('getBlocks')->willReturn([]);
        $this->webspacesResource->expects($this->once())->method('getWebspaces')->willReturn([]);
        $this->guidelinesResource->expects($this->once())->method('getGuidelines')->with('mywebspace')->willReturn([]);
        $this->companyContextResource->expects($this->once())->method('getCompanyContext')->willReturn([]);

        $this->tool->getContext('mywebspace');
    }

    public function testGetContextMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(GetContextTool::class, 'getContext');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'getContext() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_get_context', $instance->name);
    }
}
