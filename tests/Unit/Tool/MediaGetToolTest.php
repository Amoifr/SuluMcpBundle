<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\McpServerBundle\Tool\MediaGetTool;

class MediaGetToolTest extends TestCase
{
    private MediaManagerInterface&MockObject $mediaManager;
    private MediaGetTool $tool;

    protected function setUp(): void
    {
        $this->mediaManager = $this->createMock(MediaManagerInterface::class);
        $this->tool = new MediaGetTool($this->mediaManager);
    }

    public function testGetMediaReturnsFullDetails(): void
    {
        $media = $this->createMock(Media::class);
        $media->method('getId')->willReturn(42);
        $media->method('getTitle')->willReturn('Hero Image');
        $media->method('getDescription')->willReturn('A beautiful hero image');
        $media->method('getCopyright')->willReturn('(c) 2026 Example');
        $media->method('getMimeType')->willReturn('image/png');
        $media->method('getSize')->willReturn(54321);
        $media->method('getUrl')->willReturn('/media/42/hero.png');
        $media->method('getFormats')->willReturn([
            'sulu-100x100' => '/media/42/hero.png?v=1-0&inline=1',
            'sulu-400x400' => '/media/42/hero.png?v=1-0',
        ]);

        $this->mediaManager->method('getById')->willReturn($media);

        $result = $this->tool->getMedia(42, 'en');

        $this->assertSame(42, $result['id']);
        $this->assertSame('Hero Image', $result['title']);
        $this->assertSame('A beautiful hero image', $result['description']);
        $this->assertSame('(c) 2026 Example', $result['copyright']);
        $this->assertSame('image/png', $result['mimeType']);
        $this->assertSame(54321, $result['size']);
        $this->assertSame('/media/42/hero.png', $result['url']);
        $this->assertCount(2, $result['formats']);
    }

    public function testGetMediaReturnsErrorForMissingMedia(): void
    {
        $this->mediaManager->method('getById')->willThrowException(new \RuntimeException('Not found'));

        $result = $this->tool->getMedia(999, 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('999', $result['error']);
    }

    public function testGetMediaMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(MediaGetTool::class, 'getMedia');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'getMedia() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_media_get', $instance->name);
    }
}
