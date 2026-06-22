<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool;

use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Capabilities\Resource\BlocksResource;
use Sulu\McpServerBundle\Capabilities\Resource\ExtensionFieldsResource;
use Sulu\McpServerBundle\Capabilities\Resource\FieldValueExampleProvider;
use Sulu\McpServerBundle\Capabilities\Resource\TemplatesResource;
use Sulu\McpServerBundle\Capabilities\Resource\WebspacesResource;
use Sulu\McpServerBundle\Capabilities\Tool\GetContextTool;

final class GetContextToolTest extends TestCase
{
    public function testGetContextAddsDedupedFieldTypeLegend(): void
    {
        $templates = $this->createMock(TemplatesResource::class);
        $blocks = $this->createMock(BlocksResource::class);
        $webspaces = $this->createMock(WebspacesResource::class);
        $extensionFields = $this->createMock(ExtensionFieldsResource::class);

        $templates->method('getTemplates')->willReturn([
            'page' => [
                'default' => [
                    'key' => 'default',
                    'fields' => [
                        ['name' => 'title', 'type' => 'text_line'],
                        ['name' => 'url', 'type' => 'route'],
                        ['name' => 'blocks', 'type' => 'block', 'types' => [
                            'text' => ['key' => 'text', 'fields' => [
                                ['name' => 'content', 'type' => 'text_editor'],
                            ]],
                        ]],
                    ],
                ],
            ],
        ]);
        $blocks->method('getBlocks')->willReturn([]);
        $webspaces->method('getWebspaces')->willReturn([]);
        $extensionFields->method('getExtensionFields')->willReturn(['seo' => [], 'excerpt' => []]);

        $tool = new GetContextTool($templates, $blocks, $webspaces, new FieldValueExampleProvider(), $extensionFields);

        $result = $tool->getContext();

        // Legend present, keyed by type, listing each example/hint exactly once
        $this->assertArrayHasKey('fieldTypes', $result);
        $this->assertArrayHasKey('text_line', $result['fieldTypes']);
        $this->assertArrayHasKey('text_editor', $result['fieldTypes']);
        $this->assertSame('Example text', $result['fieldTypes']['text_line']['example']);
        $this->assertStringContainsString('<sulu-link', (string) $result['fieldTypes']['text_editor']['example']);
        $this->assertArrayHasKey('hint', $result['fieldTypes']['text_editor']);

        // Types without example data are omitted (route, block, …)
        $this->assertArrayNotHasKey('route', $result['fieldTypes']);
        $this->assertArrayNotHasKey('block', $result['fieldTypes']);

        // Fields no longer carry inline examples (deduped into the legend)
        $titleField = $result['templates']['page']['default']['fields'][0];
        $this->assertArrayNotHasKey('valueExample', $titleField);
        $this->assertArrayNotHasKey('valueHint', $titleField);

        // Extension fields advertised
        $this->assertArrayHasKey('seoFields', $result);
        $this->assertArrayHasKey('excerptFields', $result);
    }

    public function testGetContextOmitsLegendWhenNoKnownTypesPresent(): void
    {
        $templates = $this->createMock(TemplatesResource::class);
        $blocks = $this->createMock(BlocksResource::class);
        $webspaces = $this->createMock(WebspacesResource::class);
        $extensionFields = $this->createMock(ExtensionFieldsResource::class);

        $templates->method('getTemplates')->willReturn([
            'page' => ['default' => ['key' => 'default', 'fields' => [
                ['name' => 'image', 'type' => 'media_selection'],
            ]]],
        ]);
        $blocks->method('getBlocks')->willReturn([]);
        $webspaces->method('getWebspaces')->willReturn([]);
        $extensionFields->method('getExtensionFields')->willReturn(['seo' => [], 'excerpt' => []]);

        $tool = new GetContextTool($templates, $blocks, $webspaces, new FieldValueExampleProvider(), $extensionFields);

        $result = $tool->getContext();

        $this->assertSame([], $result['fieldTypes']);
        $this->assertArrayHasKey('seoFields', $result);
        $this->assertArrayHasKey('excerptFields', $result);
    }
}
