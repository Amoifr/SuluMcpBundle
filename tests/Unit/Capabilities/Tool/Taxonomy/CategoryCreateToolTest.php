<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\Capabilities\Tool\Taxonomy;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\McpServerBundle\Capabilities\Tool\Taxonomy\CategoryCreateTool;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(CategoryCreateTool::class)]
final class CategoryCreateToolTest extends TestCase
{
    private CategoryManagerInterface&MockObject $categoryManager;
    private TokenStorageInterface&MockObject $tokenStorage;
    private CategoryCreateTool $tool;

    protected function setUp(): void
    {
        $this->categoryManager = $this->createMock(CategoryManagerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tool = new CategoryCreateTool($this->categoryManager, $this->tokenStorage);
    }

    public function testCreateCategoryReturnsSuccess(): void
    {
        $this->mockAuthenticatedUser(1);

        $category = $this->createMock(CategoryInterface::class);
        $category->method('getId')->willReturn(10);
        $category->method('getKey')->willReturn('technology');

        $this->categoryManager->expects($this->once())
            ->method('save')
            ->with(['name' => 'Technology', 'locale' => 'en', 'key' => 'technology'], 1, 'en')
            ->willReturn($category);

        $result = $this->tool->createCategory('en', 'Technology', 'technology');

        $this->assertTrue($result['success']);
        $this->assertSame(10, $result['id']);
        $this->assertSame('Technology', $result['name']);
        $this->assertSame('technology', $result['key']);
    }

    public function testCreateCategoryWithParentId(): void
    {
        $this->mockAuthenticatedUser(1);

        $category = $this->createMock(CategoryInterface::class);
        $category->method('getId')->willReturn(11);
        $category->method('getKey')->willReturn('php');

        $this->categoryManager->expects($this->once())
            ->method('save')
            ->with(['name' => 'PHP', 'locale' => 'en', 'parent' => 10], 1, 'en')
            ->willReturn($category);

        $result = $this->tool->createCategory('en', 'PHP', null, 10);

        $this->assertTrue($result['success']);
    }

    public function testCreateCategoryReturnsErrorWhenNoUser(): void
    {
        $this->tokenStorage->method('getToken')->willReturn(null);

        $result = $this->tool->createCategory('en', 'Test');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No authenticated user', $result['error']);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(CategoryCreateTool::class, 'createCategory');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_category_create', $attributes[0]->newInstance()->name);
    }

    private function mockAuthenticatedUser(int $userId): void
    {
        $user = new class($userId) implements UserInterface {
            public function __construct(private readonly int $id)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'admin';
            }
        };

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->tokenStorage->method('getToken')->willReturn($token);
    }
}
