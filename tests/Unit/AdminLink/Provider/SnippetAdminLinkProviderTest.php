<?php

declare(strict_types=1);

namespace Sulu\Bundle\McpBundle\Tests\Unit\AdminLink\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\View;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\AdminBundle\Exception\ViewNotFoundException;
use Sulu\Bundle\McpBundle\AdminLink\Provider\SnippetAdminLinkProvider;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

#[CoversClass(SnippetAdminLinkProvider::class)]
final class SnippetAdminLinkProviderTest extends TestCase
{
    private ViewRegistry&MockObject $viewRegistry;
    private SnippetAdminLinkProvider $provider;

    protected function setUp(): void
    {
        $this->viewRegistry = $this->createMock(ViewRegistry::class);
        $this->viewRegistry->method('findViewByName')->willReturnCallback(
            static function (string $name): View {
                if (SnippetAdmin::EDIT_TABS_VIEW === $name) {
                    return new View($name, '/snippets/:locale/:id', 'form');
                }

                throw new ViewNotFoundException($name);
            }
        );

        $this->provider = new SnippetAdminLinkProvider($this->viewRegistry);
    }

    public function testGetTypeReturnsSnippet(): void
    {
        $this->assertSame('snippet', $this->provider->getType());
    }

    public function testBuildPathReturnsCorrectPath(): void
    {
        $result = $this->provider->buildPath([
            'locale' => 'en',
            'uuid' => 'snippet-uuid',
        ]);

        $this->assertSame('/snippets/en/snippet-uuid', $result);
    }

    /**
     * @return array<string, array<array<string, string>>>
     */
    public static function missingRequiredKeyProvider(): array
    {
        return [
            'missing locale' => [['uuid' => 'snippet-uuid']],
            'missing uuid' => [['locale' => 'en']],
            'empty locale' => [['locale' => '', 'uuid' => 'snippet-uuid']],
            'empty uuid' => [['locale' => 'en', 'uuid' => '']],
            'empty context' => [[]],
        ];
    }

    /**
     * @param array<string, string> $context
     */
    #[DataProvider('missingRequiredKeyProvider')]
    public function testBuildPathReturnsNullWhenRequiredKeyMissing(array $context): void
    {
        $this->assertNull($this->provider->buildPath($context));
    }
}
