# Phase 3: Page Content Management - Context

**Gathered:** 2026-03-30 (assumptions mode)
**Status:** Ready for planning

<domain>
## Phase Boundary

AI clients can create, read, update, delete, and publish pages with full block management -- the complete page content workflow. Also includes a page tree tool for navigating site structure (admin-style hierarchy view). This is the first content mutation phase -- proving the full create-edit-publish cycle on pages before extending to articles and other content types in Phase 4.

</domain>

<decisions>
## Implementation Decisions

### Content Read/Write Approach
- **D-01:** Page CRUD tools dispatch Sulu 3.0 message classes (`CreatePageMessage`, `ModifyPageMessage`, `RemovePageMessage`) via `MessageBusInterface` with `EnableFlushStamp` -- not via REST API or direct Doctrine access
- **D-02:** Page reading uses `ContentResolver` or `ContentManager` to retrieve pages with resolved content and blocks
- **D-03:** All page tools follow the established pattern: `#[McpTool]` attribute, constructor injection of Sulu services, webspace and locale as required parameters

### Block Operations
- **D-04:** Block add/remove/reorder (BLCK-01, BLCK-02, BLCK-03) implemented as modifications to the page's content data structure via `ModifyPageMessage` -- blocks are part of the page content payload (JSON columns), not independent entities
- **D-05:** Separate MCP tools for block operations (`sulu_block_add`, `sulu_block_remove`, `sulu_block_reorder`) that internally read current page content, modify the blocks array, then dispatch `ModifyPageMessage`
- **D-06:** BLCK-04 (dynamic block type discovery) is already covered by Phase 2's `sulu://blocks` resource -- Phase 3 does not need to duplicate this. AI reads `sulu://blocks` to discover available types before calling `sulu_block_add`

### Page Tree Tool
- **D-07:** A `sulu_page_tree` tool exposes the hierarchical page tree as shown in Sulu admin -- enables AI to understand site structure and pick parent pages when creating/moving content
- **D-08:** Each tree node includes: UUID, title, URL, page type (internal/external/link), has-children flag, parent UUID, depth, workflow state (draft/published), and locale availability
- **D-09:** Accepts webspace and locale parameters. Returns the full tree (not depth-limited like the Phase 2 sitemap resource which is for navigation context)

### Publish/Unpublish
- **D-10:** Publishing and unpublishing use `ApplyWorkflowTransitionPageMessage` with transition names "publish" and "unpublish" dispatched via message bus
- **D-11:** Only two workflow states: draft and published (out-of-scope confirms no custom workflow states in v1)

### Tool Organization
- **D-12:** Keep flat `src/Tool/` directory structure with naming prefix convention (`PageGetTool.php`, `PageCreateTool.php`, `BlockAddTool.php`, etc.) -- consistent with existing tools

### MCP Prompt for Guideline Generation
- **D-13:** The MCP Prompt deferred from Phase 2 (D-15) that guides AI through analyzing pages to generate content guidelines is in scope for Phase 3 -- it depends on page reading tools which are being built here

### Claude's Discretion
- Exact `ContentResolver`/`ContentManager` method usage for reading pages (needs research)
- Exact `CreatePageMessage`/`ModifyPageMessage` constructor parameters and content data structure (needs research)
- How page list/search filtering works (webspace, locale, template filters via SEAL or ContentManager query API)
- Error handling for invalid UUIDs, missing pages, permission violations
- Page tree implementation (NavigationRepositoryInterface vs ContentManager query)
- Whether `sulu_page_tree` should support optional depth limiting or subtree queries (e.g., children of a specific UUID)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Sulu 3.0 Service Layer
- `.planning/research/STACK.md` -- Sulu 3.0 message classes (CreatePageMessage, ModifyPageMessage, RemovePageMessage, ApplyWorkflowTransitionPageMessage), ContentManager, ContentResolver, message bus dispatch pattern with EnableFlushStamp
- `.planning/research/ARCHITECTURE.md` -- Four-layer architecture, anti-patterns (especially #5: no direct Doctrine for Sulu-owned content), tool naming conventions (`sulu_` prefix)

### Existing Implementation Patterns
- `src/Tool/PingTool.php` -- Established MCP tool pattern: `#[McpTool]` attribute, constructor injection
- `src/Tool/UpdateGuidelinesTool.php` -- Write tool pattern with input validation
- `src/Resource/BlocksResource.php` -- Block type discovery (BLCK-04 already implemented here)
- `src/Resource/TemplatesResource.php` -- Template metadata introspection pattern

### Phase 2 Context
- `.planning/phases/02-context-discovery-guidelines/02-CONTEXT.md` -- D-15 deferred MCP Prompt to this phase

### Project Context
- `.planning/PROJECT.md` -- Vision, constraints, key decisions
- `.planning/REQUIREMENTS.md` -- Phase 3 requirements: PAGE-01-05, BLCK-01-04, PUBL-01-02

### Pitfalls
- `.planning/research/PITFALLS.md` -- MCP SDK pre-1.0 issues, transport pitfalls, security considerations

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `src/Tool/PingTool.php` -- Tool pattern with `#[McpTool]` attribute, webspace/locale parameter handling via `WebspaceManagerInterface`
- `src/Tool/UpdateGuidelinesTool.php` -- Write tool pattern with entity persistence and input validation
- `src/Tool/GetContextTool.php` -- Read tool pattern that aggregates data from multiple sources
- `src/Resource/BlocksResource.php` -- Block type discovery from template metadata (reuse for BLCK-04, already covers the requirement)
- `src/Resource/TemplatesResource.php` -- Template field schema introspection (useful reference for understanding page content structure)
- `src/DependencyInjection/Configuration.php` -- Config tree for adding new tool configuration
- `config/services.yaml` -- Service registration pattern (autowire/autoconfigure enabled)

### Established Patterns
- MCP tool/resource discovery via PHP 8 attributes -- `#[McpTool]` and `#[McpResource]`
- Bundle config -> container parameters -> constructor injection
- All PHP classes: `declare(strict_types=1)`, namespace `Sulu\McpServerBundle\{subdomain}`
- Tool naming: `sulu_` prefix (e.g., `sulu_ping`, `sulu_update_guidelines`)

### Integration Points
- New tools in `src/Tool/`: PageGetTool, PageListTool, PageCreateTool, PageUpdateTool, PageDeleteTool, PageTreeTool, BlockAddTool, BlockRemoveTool, BlockReorderTool, PagePublishTool, PageUnpublishTool
- Sulu services to inject: `MessageBusInterface`, `ContentResolver`/`ContentManager`, `WebspaceManagerInterface`
- MCP Prompt class (new pattern): `src/Prompt/` directory for guideline generation prompt

</code_context>

<specifics>
## Specific Ideas

- Page tree tool should mirror what Sulu admin shows (screenshot reference) -- hierarchical tree with published/draft status visible per node
- The page tree is the primary navigation tool for AI when working with page content -- more actionable than the sitemap resource
- MCP Prompt for guideline generation (deferred D-15): reads existing pages to analyze tone/style, then calls `sulu_update_guidelines` to persist. This closes the loop from Phase 2.

</specifics>

<deferred>
## Deferred Ideas

- Page move/copy/reorder operations -- could be added as separate tools later (Sulu has MovePageMessage, CopyPageMessage, OrderPageMessage)
- Page version history / restore -- Sulu has RestorePageVersionMessage but version browsing is out of scope (v2)
- Locale copy for pages -- CopyLocalePageMessage exists but is a translation workflow concern

</deferred>

---

*Phase: 03-page-content-management*
*Context gathered: 2026-03-30*
