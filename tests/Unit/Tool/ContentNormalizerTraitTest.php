<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\McpServerBundle\Tool\ContentNormalizerTrait;

#[CoversClass(ContentNormalizerTrait::class)]
final class ContentNormalizerTraitTest extends TestCase
{
    use ContentNormalizerTrait;

    public function testCompactContentStripsUnusedFields(): void
    {
        $normalized = [
            'title' => 'Test',
            'id' => 'duplicate-of-uuid',
            'version' => 0,
            'stage' => 'draft',
            'customizeWebspaceSettings' => false,
            'additionalWebspaces' => [],
            'excerptAudienceTargetGroups' => [],
            'excerptSegment' => null,
            'lastModified' => null,
            'lastModifiedEnabled' => false,
        ];

        $result = $this->compactContent($normalized);

        $this->assertSame('Test', $result['title']);
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('version', $result);
        $this->assertArrayNotHasKey('stage', $result);
        $this->assertArrayNotHasKey('customizeWebspaceSettings', $result);
        $this->assertArrayNotHasKey('additionalWebspaces', $result);
        $this->assertArrayNotHasKey('excerptAudienceTargetGroups', $result);
        $this->assertArrayNotHasKey('excerptSegment', $result);
        $this->assertArrayNotHasKey('lastModified', $result);
        $this->assertArrayNotHasKey('lastModifiedEnabled', $result);
    }

    public function testCompactContentRemovesNullAndEmpty(): void
    {
        $normalized = [
            'title' => 'Test',
            'changer' => null,
            'creator' => null,
            'shadowLocales' => [],
            'seo' => ['title' => '', 'keywords' => '', 'description' => ''],
        ];

        $result = $this->compactContent($normalized);

        $this->assertSame('Test', $result['title']);
        $this->assertArrayNotHasKey('changer', $result);
        $this->assertArrayNotHasKey('creator', $result);
        $this->assertArrayNotHasKey('shadowLocales', $result);
        $this->assertArrayNotHasKey('seo', $result);
    }

    public function testCompactContentKeepsFalseAndZero(): void
    {
        $normalized = [
            'publishedState' => false,
            'seoNoFollow' => false,
            'author' => 0,
        ];

        $result = $this->compactContent($normalized);

        $this->assertFalse($result['publishedState']);
        $this->assertFalse($result['seoNoFollow']);
        $this->assertSame(0, $result['author']);
    }

    public function testCompactContentSummarizesBlocks(): void
    {
        $normalized = [
            'title' => 'Test',
            'blocks' => [
                [
                    '_id' => 'abc',
                    'type' => 'section',
                    'title' => 'My Section',
                    'description' => '<p>Long HTML content...</p>',
                    'blocks' => [
                        ['_id' => 'x', 'type' => 'text', 'description' => '<p>nested</p>'],
                        ['_id' => 'y', 'type' => 'image', 'src' => '/img.jpg'],
                    ],
                    'settings' => [],
                ],
                [
                    '_id' => 'def',
                    'type' => 'text',
                    'title' => 'Standalone',
                    'description' => '<p>Some content</p>',
                ],
            ],
        ];

        $result = $this->compactContent($normalized, ['blocks']);

        $this->assertCount(2, $result['blocks']);

        // First block: section with sub-blocks
        $this->assertSame(0, $result['blocks'][0]['index']);
        $this->assertSame('abc', $result['blocks'][0]['_id']);
        $this->assertSame('section', $result['blocks'][0]['type']);
        $this->assertSame('My Section', $result['blocks'][0]['title']);
        $this->assertSame(2, $result['blocks'][0]['blockCount']);
        $this->assertArrayNotHasKey('description', $result['blocks'][0]);
        $this->assertArrayNotHasKey('settings', $result['blocks'][0]);

        // Second block: no sub-blocks
        $this->assertSame(1, $result['blocks'][1]['index']);
        $this->assertSame('def', $result['blocks'][1]['_id']);
        $this->assertArrayNotHasKey('blockCount', $result['blocks'][1]);
    }

    public function testDetectBlockProperties(): void
    {
        $normalized = [
            'title' => 'Test',
            'blocks' => [
                ['_id' => 'a', 'type' => 'text'],
            ],
            'homeBlocks' => [
                ['_id' => 'b', 'type' => 'section'],
            ],
            'description' => '<p>not a block</p>',
            'tags' => ['tag1', 'tag2'],
        ];

        $result = $this->detectBlockProperties($normalized);

        $this->assertContains('blocks', $result);
        $this->assertContains('homeBlocks', $result);
        $this->assertNotContains('description', $result);
        $this->assertNotContains('tags', $result);
    }

    public function testDetectBlockPropertiesIgnoresEmptyArrays(): void
    {
        $normalized = [
            'blocks' => [],
            'tags' => [],
        ];

        $result = $this->detectBlockProperties($normalized);

        $this->assertEmpty($result);
    }
}
