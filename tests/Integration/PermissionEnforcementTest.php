<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\McpBundle\Tests\Integration;

use Mcp\Capability\Registry;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\CallToolHandler;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\McpBundle\Capabilities\FilteredRegistry;
use Sulu\Bundle\McpBundle\Capabilities\PermissionAwareCallToolHandler;
use Sulu\Bundle\McpBundle\Security\Permission\ArticleSecurityContextResolver;
use Sulu\Bundle\McpBundle\Security\Permission\ToolPermissionChecker;
use Sulu\Bundle\McpBundle\Security\Permission\ToolVisibilityResolver;
use Sulu\Bundle\McpBundle\Security\Permission\WebspacePermissionResolver;
use Sulu\Bundle\McpBundle\Tests\Support\StubGroupProvider;
use Sulu\Bundle\McpBundle\Tests\Support\StubToolPermissionChecker;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Boots the real permission-enforcement chain (PermissionAwareCallToolHandler,
 * FilteredRegistry, ToolVisibilityResolver -- real instances, not mocks) over a
 * real Registry, guarding against a past bug where this logic was unit-tested
 * green but never actually wired into the real call path.
 */
#[CoversClass(PermissionAwareCallToolHandler::class)]
#[CoversClass(FilteredRegistry::class)]
#[CoversClass(ToolVisibilityResolver::class)]
final class PermissionEnforcementTest extends TestCase
{
    private const ALLOWLIST = ['sulu_ping', 'sulu_get_context'];

    /**
     * Covers the representative tool shapes: static-context (sulu_tag_create,
     * sulu_tag_list) and objectResolved with the ANY_WEBSPACE sentinel
     * (sulu_page_get). `sulu_mystery_tool` is deliberately absent, to exercise
     * the fail-closed path.
     *
     * @var array<string, array{name: string, requirements: list<array{context: string, permission: string}>, contextArgument: ?string, contextResolver: ?string, objectResolved: bool, discoveryContexts: list<string>}>
     */
    private const MAP = [
        'sulu_tag_create' => [
            'name' => 'sulu_tag_create',
            'requirements' => [['context' => 'sulu.settings.tags', 'permission' => PermissionTypes::ADD]],
            'contextArgument' => null, 'contextResolver' => null,
            'objectResolved' => false, 'discoveryContexts' => [],
        ],
        'sulu_tag_list' => [
            'name' => 'sulu_tag_list',
            'requirements' => [['context' => 'sulu.settings.tags', 'permission' => PermissionTypes::VIEW]],
            'contextArgument' => null, 'contextResolver' => null,
            'objectResolved' => false, 'discoveryContexts' => [],
        ],
        'sulu_page_get' => [
            'name' => 'sulu_page_get',
            'requirements' => [['context' => 'sulu.webspaces.#context#', 'permission' => PermissionTypes::EDIT]],
            'contextArgument' => null, 'contextResolver' => null,
            'objectResolved' => true, 'discoveryContexts' => [WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT],
        ],
    ];

    public function testDeniedToolCallReturnsIsErrorWithReason(): void
    {
        $handler = new PermissionAwareCallToolHandler(
            $this->buildRegistry(),
            new ReferenceHandler(null),
            new StubToolPermissionChecker([]),
            $this->webspaceResolver([]),
            new ArticleSecurityContextResolver(StubGroupProvider::singleGroup()),
            self::MAP,
            [],
            self::ALLOWLIST,
        );

        $response = $handler->handle($this->callRequest('sulu_tag_create', ['name' => 'x']), $this->session());

        self::assertInstanceOf(Response::class, $response);
        $result = $response->result;
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertStringContainsString('Permission denied', $this->textOf($result));
        self::assertStringContainsString('sulu.settings.tags', $this->textOf($result));
    }

    /**
     * Denied even though never registered in the registry -- the preflight
     * rejects before any registry lookup happens.
     */
    public function testUndeclaredNonAllowlistedToolIsDeniedFailClosed(): void
    {
        $handler = new PermissionAwareCallToolHandler(
            $this->buildRegistry(),
            new ReferenceHandler(null),
            new StubToolPermissionChecker([]),
            $this->webspaceResolver([]),
            new ArticleSecurityContextResolver(StubGroupProvider::singleGroup()),
            self::MAP,
            [],
            self::ALLOWLIST,
        );

        $response = $handler->handle($this->callRequest('sulu_mystery_tool', []), $this->session());

        self::assertInstanceOf(Response::class, $response);
        $result = $response->result;
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }

    public function testFilteredRegistryHidesDeniedShowsPermittedAndAllowlisted(): void
    {
        $checker = new StubToolPermissionChecker([
            ['sulu.settings.tags', PermissionTypes::VIEW],
        ]);
        $visibilityResolver = new ToolVisibilityResolver(
            self::MAP,
            $checker,
            $this->webspaceResolver([]),
            new ArticleSecurityContextResolver(StubGroupProvider::singleGroup()),
            [],
            self::ALLOWLIST,
        );
        $filtered = new FilteredRegistry($this->buildRegistry(), $visibilityResolver);

        $names = array_keys((array) $filtered->getTools(null, null)->getArrayCopy());

        self::assertContains('sulu_ping', $names);
        self::assertContains('sulu_tag_list', $names);
        self::assertNotContains('sulu_tag_create', $names);
    }

    /**
     * Discovery errs toward showing -- an objectResolved tool appears if
     * EDIT is granted on ANY webspace. Contrasted against zero grants (tool
     * disappears) to prove the sentinel path drives visibility.
     */
    public function testDec2ToolPermittedInOneWebspaceIsPresentInToolsList(): void
    {
        $checker = new StubToolPermissionChecker([]); // no static-context grants needed here
        $visibilityResolver = new ToolVisibilityResolver(
            self::MAP,
            $checker,
            $this->webspaceResolver(['example']), // 'example' grants EDIT, 'blog' denies it
            new ArticleSecurityContextResolver(StubGroupProvider::singleGroup()),
            [],
            self::ALLOWLIST,
        );
        $filtered = new FilteredRegistry($this->buildRegistry(), $visibilityResolver);

        $names = array_keys((array) $filtered->getTools(null, null)->getArrayCopy());
        self::assertContains('sulu_page_get', $names);

        $noWebspaceVisibilityResolver = new ToolVisibilityResolver(
            self::MAP,
            $checker,
            $this->webspaceResolver([]), // no webspace grants EDIT at all
            new ArticleSecurityContextResolver(StubGroupProvider::singleGroup()),
            [],
            self::ALLOWLIST,
        );
        $filteredNone = new FilteredRegistry($this->buildRegistry(), $noWebspaceVisibilityResolver);

        $namesNone = array_keys((array) $filteredNone->getTools(null, null)->getArrayCopy());
        self::assertNotContains('sulu_page_get', $namesNone);
    }

    /**
     * Half of the dead-code-path regression guard: the SAME denied call succeeds
     * through the SDK's bare CallToolHandler (no permission wrapper), proving
     * enforcement depends on that wrapper being present. The other half,
     * testHandlerIsRegisteredAsMcpRequestHandler, proves it's actually wired in.
     */
    public function testNegativeControlBareCallToolHandlerDoesNotEnforcePermissions(): void
    {
        $registry = $this->buildRegistry();
        $bareHandler = new CallToolHandler($registry, new ReferenceHandler(null));

        $response = $bareHandler->handle($this->callRequest('sulu_tag_create', ['name' => 'x']), $this->session());

        self::assertInstanceOf(Response::class, $response);
        $result = $response->result;
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse(
            $result->isError,
            'Bare CallToolHandler executed the denied tool successfully -- nothing in this path checks permissions. '
            .'This demonstrates why enforcement matters: without PermissionAwareCallToolHandler in front of it, '
            .'this is the outcome every denied call would have. Whether that handler is actually wired into the '
            .'real dispatch chain is asserted separately by testHandlerIsRegisteredAsMcpRequestHandler.',
        );
    }

    /**
     * Other half of the guard: loads the real config/services.yaml into a bare
     * ContainerBuilder (no compile needed -- tags are already parsed) and
     * asserts the handler carries mcp.request_handler with priority 100, which
     * is what wins dispatch ahead of the SDK's default CallToolHandler.
     */
    public function testHandlerIsRegisteredAsMcpRequestHandler(): void
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.yaml');

        self::assertTrue($container->hasDefinition(PermissionAwareCallToolHandler::class));

        $tags = $container->getDefinition(PermissionAwareCallToolHandler::class)->getTag('mcp.request_handler');
        self::assertNotSame([], $tags, 'PermissionAwareCallToolHandler is missing its mcp.request_handler tag in services.yaml.');
        self::assertSame(100, $tags[0]['priority'] ?? null);

        // Documents the autoconfiguration path (see docblock above).
        self::assertTrue(
            (new \ReflectionClass(PermissionAwareCallToolHandler::class))->implementsInterface(RequestHandlerInterface::class),
        );
    }

    /**
     * Trivial working handlers, so the same registry is reused by both the
     * enforced path (denied before the handler runs) and the negative control
     * (handler actually executes and succeeds).
     */
    private function buildRegistry(): Registry
    {
        $registry = new Registry();
        $registry->registerTool($this->tool('sulu_ping'), static fn (): string => 'pong');
        $registry->registerTool($this->tool('sulu_tag_create'), static fn (): string => 'tag created');
        $registry->registerTool($this->tool('sulu_tag_list'), static fn (): array => ['tags' => []]);
        $registry->registerTool($this->tool('sulu_page_get'), static fn (): string => 'page');

        return $registry;
    }

    private function tool(string $name): Tool
    {
        // properties must encode as stdClass ({}), not [] — the negative control's real
        // CallToolHandler runs Opis json-schema validation, which rejects properties as [].
        return new Tool($name, ['type' => 'object', 'properties' => new \stdClass()], null, null);
    }

    private function callRequest(string $name, array $arguments): CallToolRequest
    {
        return CallToolRequest::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);
    }

    private function session(): SessionInterface&MockObject
    {
        return $this->createMock(SessionInterface::class);
    }

    /**
     * WebspacePermissionResolver is final, so build it for real over mocked
     * collaborators, mirroring PermissionAwareCallToolHandlerTest.
     *
     * @param list<string> $grantedWebspaceKeys webspace keys on which EDIT is granted
     */
    private function webspaceResolver(array $grantedWebspaceKeys): WebspacePermissionResolver
    {
        $webspaces = [];
        foreach (['example', 'blog'] as $key) {
            $webspace = new Webspace();
            $webspace->setKey($key);
            $webspaces[$key] = $webspace;
        }

        $webspaceManager = $this->createMock(WebspaceManagerInterface::class);
        $webspaceManager->method('getWebspaceCollection')->willReturn(new WebspaceCollection($webspaces));

        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->willReturnCallback(
            static fn ($condition, string $permission): bool => \in_array(
                str_replace('sulu.webspaces.', '', $condition->getSecurityContext()),
                $grantedWebspaceKeys,
                true,
            ),
        );

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));
        $tokenStorage->method('getToken')->willReturn($token);

        return new WebspacePermissionResolver($webspaceManager, new ToolPermissionChecker($securityChecker, $tokenStorage));
    }

    private function textOf(CallToolResult $result): string
    {
        $first = $result->content[0] ?? null;
        self::assertInstanceOf(TextContent::class, $first);

        return (string) $first->text;
    }
}
