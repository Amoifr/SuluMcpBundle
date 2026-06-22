<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tests\Unit\AdminLink\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\View;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\AdminBundle\Exception\ViewNotFoundException;
use Sulu\Bundle\CategoryBundle\Admin\CategoryAdmin;
use Sulu\McpServerBundle\AdminLink\Provider\CategoryAdminLinkProvider;

#[CoversClass(CategoryAdminLinkProvider::class)]
final class CategoryAdminLinkProviderTest extends TestCase
{
    private ViewRegistry&MockObject $viewRegistry;
    private CategoryAdminLinkProvider $provider;

    protected function setUp(): void
    {
        $this->viewRegistry = $this->createMock(ViewRegistry::class);
        $this->viewRegistry->method('findViewByName')->willReturnCallback(
            static function (string $name): View {
                if (CategoryAdmin::EDIT_FORM_VIEW === $name) {
                    return new View($name, '/categories/:locale/:id', 'form');
                }

                throw new ViewNotFoundException($name);
            }
        );

        $this->provider = new CategoryAdminLinkProvider($this->viewRegistry);
    }

    public function testGetTypeReturnsCategory(): void
    {
        $this->assertSame('category', $this->provider->getType());
    }

    public function testBuildPathWithIntegerId(): void
    {
        $result = $this->provider->buildPath(['locale' => 'en', 'id' => 3]);

        $this->assertSame('/categories/en/3', $result);
    }

    public function testBuildPathWithStringId(): void
    {
        $result = $this->provider->buildPath(['locale' => 'en', 'id' => '3']);

        $this->assertSame('/categories/en/3', $result);
    }

    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public static function invalidContextProvider(): array
    {
        return [
            'missing locale' => [['id' => 3]],
            'missing id' => [['locale' => 'en']],
            'empty locale' => [['locale' => '', 'id' => 3]],
            'empty string id' => [['locale' => 'en', 'id' => '']],
            'zero int id' => [['locale' => 'en', 'id' => 0]],
            'negative int id' => [['locale' => 'en', 'id' => -2]],
            'empty context' => [[]],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('invalidContextProvider')]
    public function testBuildPathReturnsNullForInvalidContext(array $context): void
    {
        $this->assertNull($this->provider->buildPath($context));
    }
}
