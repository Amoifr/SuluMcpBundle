# Phase 2: Context Discovery & Guidelines - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

AI clients can discover the CMS structure (templates, block types, webspace config, sitemap) and receive content guidelines that shape on-brand content generation. Phase 2 builds the context discovery layer and the content guidelines system — including read AND write for guidelines so AI-generated guidelines can be saved back. No page/article content tools yet (Phase 3).

</domain>

<decisions>
## Implementation Decisions

### MCP Resources — URI Scheme
- **D-01:** All resources use the `sulu://` URI scheme for consistency with GUID-03/04 which already define `sulu://guidelines/{webspace}` and `sulu://context/company`
- **D-02:** Templates and block types are **global resources with webspace filter** (not webspace-scoped URIs): `sulu://templates`, `sulu://blocks` — webspace passed as query/filter parameter at read time
- **D-03:** Webspace config exposed as `sulu://webspaces` — returns all webspaces (RSRC-03)
- **D-04:** Sitemap exposed as `sulu://sitemap/{webspace}` — per-webspace (sitemap is inherently webspace-specific)

### MCP Resources — Sitemap Scope
- **D-05:** Sitemap returns **minimal fields only**: UUID, URL, title, depth — enough for AI to understand site structure and navigate to specific pages
- **D-06:** Sitemap is **depth-limited** — configurable, default 3 levels. Not a full tree dump. Prevents performance issues on large sites.

### Content Guidelines Storage
- **D-07:** Guidelines stored in a **Doctrine entity** (`ContentGuidelines`) — not YAML config. Must be writable at runtime so AI-generated guidelines can be saved back
- **D-08:** Entity fields: `webspace` (nullable — null = global default), `tone`, `audience`, `style`, `brand_rules`, `dos`, `don'ts` — all nullable text columns. Free-text, not enums — flexible, AI reads naturally
- **D-09:** Override resolution: per-webspace guideline overrides merge with global defaults (GUID-02). Global row has `webspace = null`
- **D-10:** An **MCP write tool** (`sulu_update_guidelines`) is included in Phase 2 so AI can persist generated guidelines. This is the write counterpart to the `sulu://guidelines/{webspace}` read resource

### Company Context Storage
- **D-11:** Company context stored in a **separate Doctrine entity** (`CompanyContext`) — distinct from content guidelines
- **D-12:** Entity fields: `company_name`, `description`, `industry`, `website`, `key_products` — all nullable text
- **D-13:** An **MCP write tool** (`sulu_update_company_context`) is included so AI can populate/update company context
- **D-14:** Exposed as read-only MCP resource at `sulu://context/company` (GUID-04)

### MCP Prompt for Guideline Generation
- **D-15:** The MCP Prompt that guides AI through guideline generation (analyzing pages/articles to produce guidelines) is **deferred to Phase 3** — it depends on page/article reading tools that don't exist until Phase 3

### Claude's Discretion
- Exact Doctrine entity class names and table names
- Doctrine migration strategy (bundle migration vs project migration)
- Template discovery implementation (StructureMetadataFactory vs StructureFactory)
- Block type introspection approach (derive from template block properties)
- Error handling when webspace key is invalid for sitemap/resources
- Whether sitemap depth config lives in bundle config or as a per-request parameter

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### MCP Protocol & Bundle
- `.planning/research/STACK.md` — MCP PHP SDK details, symfony/mcp-bundle resource attributes (`#[McpResource]`), Streamable HTTP transport, resource template limitations (issue #9 — use `#[McpResource]` not `#[McpResourceTemplate]`)
- `.planning/research/ARCHITECTURE.md` — Four-layer architecture, component boundaries, how resources fit alongside tools

### Sulu 3.x Services
- `.planning/research/STACK.md` §Sulu 3.0 Service Layer — WebspaceManagerInterface, StructureMetadataFactory, ContentManager
- `.planning/research/FEATURES.md` — Sulu feature list, template and block type system, webspace architecture

### Project Context
- `.planning/PROJECT.md` — Vision, constraints, key decisions
- `.planning/REQUIREMENTS.md` §MCP Resources (RSRC-01–04) and §Content Guidelines (GUID-01–04) — acceptance criteria for this phase

### Pitfalls
- `.planning/research/PITFALLS.md` — Known issues with symfony/mcp-bundle pre-1.0, transport pitfalls

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `src/Tool/PingTool.php` — Established pattern for MCP tools: `#[McpTool]` attribute on a method, inject Sulu services via constructor, return `array<string, mixed>`. Resource classes will follow same structure with `#[McpResource]`.
- `src/DependencyInjection/Configuration.php` — Config tree structure. New sitemap depth config (`sitemap.max_depth`) can be added here.
- `src/DependencyInjection/SuluMcpServerExtension.php` — How config values become container parameters. Depth param follows same pattern.
- `config/services.yaml` — Autowire/autoconfigure enabled. New Resource and Tool classes need to be registered here.
- `WebspaceManagerInterface` — Already proven in PingTool, injected via constructor. Reuse directly for RSRC-03 (webspace config) and sitemap.

### Established Patterns
- MCP tool/resource discovery via PHP 8 attributes — follow `#[McpTool]` pattern from PingTool, apply `#[McpResource]` for resources
- Bundle config → container parameters → constructor injection (see `McpAuthenticationListener`)
- All PHP classes: `declare(strict_types=1)`, namespace `Sulu\McpServerBundle\{subdomain}`

### Integration Points
- New classes: `src/Resource/` directory (TemplatesResource, BlocksResource, WebspacesResource, SitemapResource, GuidelinesResource, CompanyContextResource)
- New tool: `src/Tool/UpdateGuidelinesTool.php`, `src/Tool/UpdateCompanyContextTool.php`
- New entities: `src/Entity/ContentGuidelines.php`, `src/Entity/CompanyContext.php`
- Doctrine: already available as Sulu dependency. Bundle will need a Doctrine migration.
- Config extension: add `sitemap.max_depth` (default: 3) to `Configuration.php`

</code_context>

<specifics>
## Specific Ideas

- AI-generated guidelines is the primary use case for the write tools: Claude reads existing pages/articles (Phase 3 tools), analyzes tone/style/brand, then calls `sulu_update_guidelines` to persist. The MCP Prompt template for this workflow ships in Phase 3.
- Guidelines write tool makes Phase 2 interactive — not just read-only discovery

</specifics>

<deferred>
## Deferred Ideas

- MCP Prompt for guideline generation — Phase 3 (depends on page/article reading tools)
- Admin UI for guidelines management — v2 (ADVN requirements)
- More general "context store" for arbitrary context entries — not pursued

</deferred>

---

*Phase: 02-context-discovery-guidelines*
*Context gathered: 2026-03-30*
