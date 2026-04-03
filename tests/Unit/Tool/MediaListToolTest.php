<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\McpServerBundle\Tool\MediaListTool;

class MediaListToolTest extends TestCase
{
    private MediaManagerInterface&MockObject $mediaManager;
    private MediaListTool $tool;

    protected function setUp(): void
    {
        $this->mediaManager = $this->createMock(MediaManagerInterface::class);
        $this->tool = new MediaListTool($this->mediaManager);
    }

    public function testListMediaReturnsFormattedResults(): void
    {
        $media1 = $this->createMock(Media::class);
        $media1->method('getId')->willReturn(1);
        $media1->method('getTitle')->willReturn('Photo 1');
        $media1->method('getMimeType')->willReturn('image/jpeg');
        $media1->method('getSize')->willReturn(12345);
        $media1->method('getUrl')->willReturn('/media/1/photo1.jpg');

        $media2 = $this->createMock(Media::class);
        $media2->method('getId')->willReturn(2);
        $media2->method('getTitle')->willReturn('Document');
        $media2->method('getMimeType')->willReturn('application/pdf');
        $media2->method('getSize')->willReturn(67890);
        $media2->method('getUrl')->willReturn('/media/2/document.pdf');

        $this->mediaManager->method('get')->willReturn([$media1, $media2]);
        $this->mediaManager->method('getCount')->willReturn(10);

        $result = $this->tool->listMedia('en');

        $this->assertCount(2, $result['media']);
        $this->assertSame(10, $result['total']);
        $this->assertSame(20, $result['limit']);
        $this->assertSame(0, $result['offset']);
        $this->assertSame(1, $result['media'][0]['id']);
        $this->assertSame('Photo 1', $result['media'][0]['title']);
        $this->assertSame('image/jpeg', $result['media'][0]['mimeType']);
    }

    public function testListMediaPassesFilters(): void
    {
        $this->mediaManager
            ->expects($this->once())
            ->method('get')
            ->with(
                'de',
                $this->callback(fn (array $filter): bool => 5 === $filter['collection']
                    && 'test' === $filter['search']
                    && ['image'] === $filter['types']),
                10,
                5,
            )
            ->willReturn([]);
        $this->mediaManager->method('getCount')->willReturn(0);

        $this->tool->listMedia('de', 5, 'test', ['image'], 10, 5);
    }

    public function testListMediaDefaultsToNoFilters(): void
    {
        $this->mediaManager
            ->expects($this->once())
            ->method('get')
            ->with(
                'en',
                [],
                20,
                0,
            )
            ->willReturn([]);
        $this->mediaManager->method('getCount')->willReturn(0);

        $this->tool->listMedia('en');
    }

    public function testListMediaMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(MediaListTool::class, 'listMedia');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listMedia() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_media_list', $instance->name);
        $this->assertStringContainsString('tag-based filtering is not supported', $instance->description);
    }
}
