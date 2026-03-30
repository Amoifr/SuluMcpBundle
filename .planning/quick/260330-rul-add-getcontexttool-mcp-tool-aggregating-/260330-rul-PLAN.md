---
phase: quick
plan: 260330-rul
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Tool/GetContextTool.php
  - tests/Unit/Tool/GetContextToolTest.php
  - config/services.yaml
autonomous: true
requirements: []

must_haves:
  truths:
    - "Claude can call sulu_get_context with a webspace key and receive all CMS context in one response"
    - "Response includes templates, blocks, webspaces, guidelines, company context, and optionally sitemap"
    - "Tool delegates to existing resource classes — no duplicated logic"
  artifacts:
    - path: "src/Tool/GetContextTool.php"
      provides: "MCP tool aggregating all context resources"
      exports: ["GetContextTool"]
    - path: "tests/Unit/Tool/GetContextToolTest.php"
      provides: "Unit tests verifying delegation and output structure"
  key_links:
    - from: "src/Tool/GetContextTool.php"
      to: "src/Resource/TemplatesResource.php"
      via: "constructor injection + direct method call"
    - from: "src/Tool/GetContextTool.php"
      to: "src/Resource/GuidelinesResource.php"
      via: "constructor injection + direct method call (webspace param)"
    - from: "config/services.yaml"
      to: "Sulu\\McpServerBundle\\Tool\\GetContextTool"
      via: "service registration"
---

<objective>
Add a GetContextTool MCP tool that aggregates all CMS context into a single tool call.

Purpose: Claude's fat client can call sulu_get_context once before creating content instead of reading six separate resources. Reduces round-trips and makes context loading ergonomic.
Output: src/Tool/GetContextTool.php registered as MCP tool, with unit tests.
</objective>

<execution_context>
@/Users/johannes/Development/ai/sulu-mcp/.claude/get-shit-done/workflows/execute-plan.md
@/Users/johannes/Development/ai/sulu-mcp/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/PROJECT.md

<!-- Key interfaces the executor needs — extracted from existing resource classes -->
<interfaces>
From src/Resource/TemplatesResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class TemplatesResource {
    public function getTemplates(): array  // returns array<string, mixed>
}
```

From src/Resource/BlocksResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class BlocksResource {
    public function getBlocks(): array  // returns list<array<string, mixed>>
}
```

From src/Resource/WebspacesResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class WebspacesResource {
    public function getWebspaces(): array  // returns list<array<string, mixed>>
}
```

From src/Resource/SitemapResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class SitemapResource {
    public function getSitemap(string $webspace): array  // throws \InvalidArgumentException for unknown webspace
}
```

From src/Resource/GuidelinesResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class GuidelinesResource {
    public function getGuidelines(string $webspace): array  // pass "global" for global defaults
}
```

From src/Resource/CompanyContextResource.php:
```php
namespace Sulu\McpServerBundle\Resource;
class CompanyContextResource {
    public function getCompanyContext(): array  // returns array<string, mixed>
}
```

Existing tool pattern (from src/Tool/PingTool.php):
```php
use Mcp\Capability\Attribute\McpTool;

class PingTool {
    #[McpTool(
        name: 'sulu_ping',
        description: '...',
    )]
    public function ping(): array { ... }
}
```

Test pattern (from tests/Unit/Tool/PingToolTest.php):
- Use PHPUnit MockObject for dependencies
- Test return structure, not just "no exception"
- Test that #[McpTool] attribute is present with correct name
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Implement GetContextTool with unit tests</name>
  <files>src/Tool/GetContextTool.php, tests/Unit/Tool/GetContextToolTest.php, config/services.yaml</files>
  <behavior>
    - Test: sulu_get_context with valid webspace returns array with keys: templates, blocks, webspaces, guidelines, company_context, sitemap
    - Test: sitemap key is populated when webspace is valid (delegates to SitemapResource::getSitemap)
    - Test: sitemap key is null when SitemapResource throws \InvalidArgumentException (graceful fallback)
    - Test: #[McpTool] attribute exists with name='sulu_get_context'
    - Test: getContext() delegates to all six resource classes — each is called exactly once
  </behavior>
  <action>
    Write tests first (RED), then implement.

    **src/Tool/GetContextTool.php:**
    - Namespace: `Sulu\McpServerBundle\Tool`
    - Constructor injects all six resource classes: TemplatesResource, BlocksResource, WebspacesResource, SitemapResource, GuidelinesResource, CompanyContextResource
    - Single method `getContext(string $webspace): array` decorated with:
      ```php
      #[McpTool(
          name: 'sulu_get_context',
          description: 'Aggregates all CMS context into a single response. Returns templates, block types, webspaces, content guidelines, company context, and sitemap for the given webspace. Call this once before creating or editing content to get full CMS awareness.',
      )]
      ```
    - Method implementation:
      1. Call `$this->templatesResource->getTemplates()` → assign to `templates`
      2. Call `$this->blocksResource->getBlocks()` → assign to `blocks`
      3. Call `$this->webspacesResource->getWebspaces()` → assign to `webspaces`
      4. Call `$this->guidelinesResource->getGuidelines($webspace)` → assign to `guidelines`
      5. Call `$this->companyContextResource->getCompanyContext()` → assign to `company_context`
      6. Try `$this->sitemapResource->getSitemap($webspace)` → assign to `sitemap`. Catch `\InvalidArgumentException` and set `sitemap` to `null`
      7. Return `compact('templates', 'blocks', 'webspaces', 'guidelines', 'company_context', 'sitemap')`

    **config/services.yaml:**
    Add below the existing `UpdateCompanyContextTool` entry:
    ```yaml
    Sulu\McpServerBundle\Tool\GetContextTool: ~
    ```
    Autowire resolves all six Resource class dependencies automatically (all already registered).
  </action>
  <verify>
    <automated>cd /Users/johannes/Development/ai/sulu-mcp && composer test -- --filter GetContextTool</automated>
  </verify>
  <done>
    All GetContextToolTest cases pass. Tool registered in services.yaml.
    sulu_get_context tool appears when listing MCP tools.
  </done>
</task>

</tasks>

<verification>
Run full test suite to confirm no regressions:
`cd /Users/johannes/Development/ai/sulu-mcp && composer test`
</verification>

<success_criteria>
- GetContextTool implements #[McpTool(name: 'sulu_get_context')]
- All six resource classes injected and called; no logic duplicated
- Sitemap failure (unknown webspace) returns null gracefully, not an exception
- All tests pass including pre-existing tests
</success_criteria>

<output>
After completion, create `.planning/quick/260330-rul-add-getcontexttool-mcp-tool-aggregating-/260330-rul-SUMMARY.md`
</output>
