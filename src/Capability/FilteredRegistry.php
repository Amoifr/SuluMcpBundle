<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Capability;

use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

/**
 * Decorates Mcp's Registry to hide tools that have been disabled via
 * `dangerous_tools.*` configuration.
 *
 * Mcp has two parallel registration paths -- the Symfony DI service locator
 * (which `DangerousToolsPass` already prunes by removing service definitions)
 * and runtime attribute discovery (which scans `mcp.discovery.scan_dirs` and
 * registers every `#[McpTool]`-tagged class regardless of DI). Without this
 * decorator, discovery re-adds the dangerous tools to the registry; they then
 * show up in `tools/list` and, when called, fail with `ArgumentCountError`
 * because `ReferenceHandler` falls back to `new $class()` (no constructor
 * args) when the service locator can't resolve them.
 */
final readonly class FilteredRegistry implements RegistryInterface
{
    /**
     * @param list<string> $disabledToolNames tool names that must not appear in the registry
     */
    public function __construct(
        private RegistryInterface $inner,
        private array $disabledToolNames = [],
    ) {
    }

    public function registerTool(Tool $tool, callable|array|string $handler, bool $isManual = false): void
    {
        if (\in_array($tool->name, $this->disabledToolNames, true)) {
            return;
        }

        $this->inner->registerTool($tool, $handler, $isManual);
    }

    public function registerResource(Resource $resource, callable|array|string $handler, bool $isManual = false): void
    {
        $this->inner->registerResource($resource, $handler, $isManual);
    }

    public function registerResourceTemplate(
        ResourceTemplate $template,
        callable|array|string $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $this->inner->registerResourceTemplate($template, $handler, $completionProviders, $isManual);
    }

    public function registerPrompt(
        Prompt $prompt,
        callable|array|string $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $this->inner->registerPrompt($prompt, $handler, $completionProviders, $isManual);
    }

    public function clear(): void
    {
        $this->inner->clear();
    }

    public function getDiscoveryState(): DiscoveryState
    {
        return $this->inner->getDiscoveryState();
    }

    public function setDiscoveryState(DiscoveryState $state): void
    {
        if ([] === $this->disabledToolNames) {
            $this->inner->setDiscoveryState($state);

            return;
        }

        /** @var array<string, ToolReference> $tools */
        $tools = $state->getTools();
        foreach ($this->disabledToolNames as $disabled) {
            unset($tools[$disabled]);
        }

        $this->inner->setDiscoveryState(new DiscoveryState(
            tools: $tools,
            resources: $state->getResources(),
            prompts: $state->getPrompts(),
            resourceTemplates: $state->getResourceTemplates(),
        ));
    }

    public function hasTools(): bool
    {
        return $this->inner->hasTools();
    }

    public function getTools(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getTools($limit, $cursor);
    }

    public function getTool(string $name): ToolReference
    {
        return $this->inner->getTool($name);
    }

    public function hasResources(): bool
    {
        return $this->inner->hasResources();
    }

    public function getResources(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getResources($limit, $cursor);
    }

    public function getResource(string $uri, bool $includeTemplates = true): ResourceReference|ResourceTemplateReference
    {
        return $this->inner->getResource($uri, $includeTemplates);
    }

    public function hasResourceTemplates(): bool
    {
        return $this->inner->hasResourceTemplates();
    }

    public function getResourceTemplates(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getResourceTemplates($limit, $cursor);
    }

    public function getResourceTemplate(string $uriTemplate): ResourceTemplateReference
    {
        return $this->inner->getResourceTemplate($uriTemplate);
    }

    public function hasPrompts(): bool
    {
        return $this->inner->hasPrompts();
    }

    public function getPrompts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getPrompts($limit, $cursor);
    }

    public function getPrompt(string $name): PromptReference
    {
        return $this->inner->getPrompt($name);
    }
}
