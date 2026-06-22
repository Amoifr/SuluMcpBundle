<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Media;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\McpServerBundle\Capabilities\Tool\Media\MediaUpdateTool;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class MediaUpdateToolTest extends TestCase
{
    private MediaManagerInterface&MockObject $mediaManager;
    private TokenStorageInterface&MockObject $tokenStorage;
    private MediaUpdateTool $tool;

    protected function setUp(): void
    {
        $this->mediaManager = $this->createMock(MediaManagerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tool = new MediaUpdateTool($this->mediaManager, $this->tokenStorage);
    }

    public function testUpdateMediaSuccessfully(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage->method('getToken')->willReturn($token);

        $media = $this->createMock(Media::class);
        $media->method('getId')->willReturn(42);
        $media->method('getTitle')->willReturn('Updated Title');

        $this->mediaManager
            ->expects($this->once())
            ->method('save')
            ->with(
                null,
                $this->callback(fn (array $data): bool => 42 === $data['id']
                    && 'en' === $data['locale']
                    && 'Updated Title' === $data['title']),
                1,
            )
            ->willReturn($media);

        $result = $this->tool->updateMedia(42, 'en', 'Updated Title');

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['id']);
        $this->assertSame('Updated Title', $result['title']);
    }

    public function testUpdateMediaReturnsErrorWhenNoUser(): void
    {
        $this->tokenStorage->method('getToken')->willReturn(null);

        $result = $this->tool->updateMedia(42, 'en', 'Title');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('authenticated', $result['error']);
        $this->assertTrue(\array_key_exists('hint', $result));
        $this->assertIsString($result['hint']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testUpdateMediaPassesOnlyProvidedFields(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $media = $this->createMock(Media::class);
        $media->method('getId')->willReturn(42);
        $media->method('getTitle')->willReturn('Original');

        $this->mediaManager
            ->expects($this->once())
            ->method('save')
            ->with(
                null,
                $this->callback(fn (array $data): bool => 42 === $data['id']
                    && isset($data['copyright'])
                    && !\array_key_exists('title', $data)
                    && !\array_key_exists('description', $data)),
                1,
            )
            ->willReturn($media);

        $this->tool->updateMedia(42, 'en', null, null, '(c) 2026');
    }

    public function testUpdateMediaReturnsHintOnSaveFailure(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $this->mediaManager->method('save')->willThrowException(new \RuntimeException('Save failed'));

        $result = $this->tool->updateMedia(42, 'en', 'Title');

        $this->assertArrayHasKey('error', $result);
        $this->assertTrue(\array_key_exists('hint', $result));
        $this->assertIsString($result['hint']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testUpdateMediaMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(MediaUpdateTool::class, 'updateMedia');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'updateMedia() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_media_update', $instance->name);
    }
}
