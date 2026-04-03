# Phase 4: Extended Content Tools - Context

**Gathered:** 2026-03-31 (assumptions mode)
**Status:** Ready for planning

<domain>
## Phase Boundary

AI clients can manage articles, tags, categories, media, and read-only entities -- completing the full content management surface. Follows proven patterns from Phase 3 (pages) and extends to all remaining Sulu content types. No new infrastructure -- this phase is about breadth, not depth.

</domain>

<decisions>
## Implementation Decisions

### Article CRUD Approach
- **D-01:** Article tools mirror the page tool pattern exactly: dispatch Sulu 3.0 message classes (`CreateArticleMessage`, `ModifyArticleMessage`, `RemoveArticleMessage`) via `MessageBusInterface` with `EnableFlushStamp`, read via `ContentManagerInterface` resolve/normalize
- **D-02:** Articles are flat (no parent hierarchy) -- no `ArticleTreeTool`. Article list tool uses flat pagination with type/template filtering
- **D-03:** Article create takes a `type` parameter (e.g., "default", "blog") instead of `parentId` -- per ARTC-03 requirement
- **D-04:** Article publish/unpublish uses `ApplyWorkflowTransitionArticleMessage` with "publish"/"unpublish" transitions -- same pattern as pages
- **D-05:** Article webspace semantics TBD by researcher -- `CreateArticleMessage` constructor needs verification. Tool design: accept optional `webspace` parameter, adapt based on Sulu 3.0 article architecture findings

### Block Tools for Articles
- **D-06:** Separate article-specific block tools (`ArticleBlockAddTool`, `ArticleBlockRemoveTool`, `ArticleBlockReorderTool`) -- not generic/polymorphic refactoring of page block tools. Consistent with flat `src/Tool/` directory convention and one-class-per-action pattern
- **D-07:** Article block tools use `ModifyArticleMessage` internally (same read-modify-dispatch pattern as page block tools with `ModifyPageMessage`)
- **D-08:** Same `blockProperty` parameter pattern as page block tools for specifying which block property to operate on

### Taxonomy Tools (Tags & Categories)
- **D-09:** Tags and categories use traditional Sulu bundle managers (`TagManagerInterface`, `CategoryManagerInterface`) with direct PHP method calls -- NOT message bus. These are "Traditional Bundles" still in `src/Sulu/Bundle/`
- **D-10:** Tag tools: `TagCreateTool`, `TagListTool`, `TagDeleteTool` -- simple CRUD via `TagManagerInterface`
- **D-11:** Category tools: `CategoryCreateTool`, `CategoryListTool`, `CategoryDeleteTool` -- `CategoryListTool` returns tree structure per TAXO-05
- **D-12:** Categories are hierarchical (tree structure) -- list tool must return parent/child relationships. Exact tree API needs research

### Media Tools
- **D-13:** Media tools use `MediaManagerInterface` with direct PHP method calls -- NOT message bus
- **D-14:** Media list/search (MDIA-01): `MediaListTool` with filtering by collection, type, tags. Exact search API needs research (manager query vs SEAL)
- **D-15:** Media details (MDIA-02): `MediaGetTool` returns metadata, URLs, dimensions, format URLs
- **D-16:** Media metadata update (MDIA-03): `MediaUpdateTool` for alt text, title, copyright via `MediaManagerInterface::save()`
- **D-17:** No media upload in v1 -- requirements cover list, get details, update metadata only (MDIA-01/02/03)

### Read-Only Entity Tools
- **D-18:** Snippets (READ-02) use `ContentManager` resolve/normalize pattern (hexagonal packages) -- same infrastructure as pages/articles
- **D-19:** Navigation (READ-03) uses Sulu's WebsiteBundle navigation service -- returns navigation tree structures per webspace/locale
- **D-20:** Contacts/accounts (READ-01) use traditional repository interfaces. Tools should be conditional on ContactBundle presence (may be optional in Sulu 3.0)
- **D-21:** All read-only tools are GET-only -- no create/update/delete. Snippet CRUD is deferred to v2 (EXTD-01)

### Plan Split
- **D-22:** Follow ROADMAP split: Plan 04-01 = Article CRUD + article blocks + article publishing (message bus pattern). Plan 04-02 = Taxonomy + media + read-only entities (manager interface pattern). Grouped by service pattern to reduce context switching

### Claude's Discretion
- Exact `CreateArticleMessage`/`ModifyArticleMessage` constructor parameters (needs research)
- `ArticleRepositoryInterface` query methods for list/search
- `CategoryManagerInterface` tree retrieval approach
- `MediaManagerInterface` list/search query API
- Whether snippet reading needs webspace parameter or is global
- Contact/account repository interface names and query methods
- Navigation service interface name and tree format
- Error handling for optional bundles (ContactBundle not installed)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Sulu 3.0 Service Layer
- `.planning/research/STACK.md` -- Article message classes (CreateArticleMessage, ModifyArticleMessage, RemoveArticleMessage, ApplyWorkflowTransitionArticleMessage, CopyLocaleArticleMessage, RemoveArticleTranslationMessage, RestoreArticleVersionMessage), Snippet message classes, ContentManager, message bus dispatch with EnableFlushStamp
- `.planning/research/STACK.md` SS Sulu 3.0 Service Layer -- MediaManagerInterface (save, get, getById, delete, move, addFormatsAndUrl, getFormatUrls), TagManagerInterface, CategoryManagerInterface
- `.planning/research/ARCHITECTURE.md` -- Four-layer architecture, anti-patterns, tool naming conventions

### Existing Implementation Patterns
- `src/Tool/PageGetTool.php` -- Read tool pattern using ContentManager resolve/normalize
- `src/Tool/PageCreateTool.php` -- Create tool pattern using message bus dispatch
- `src/Tool/PageUpdateTool.php` -- Update tool pattern
- `src/Tool/PageDeleteTool.php` -- Delete tool pattern
- `src/Tool/PageListTool.php` -- List tool pattern with filtering
- `src/Tool/PagePublishTool.php` -- Publish tool pattern with ApplyWorkflowTransition
- `src/Tool/BlockAddTool.php` -- Block manipulation pattern (read-modify-dispatch)
- `src/Tool/BlockRemoveTool.php` -- Block removal pattern
- `src/Tool/BlockReorderTool.php` -- Block reorder pattern
- `src/Tool/HandleTrait.php` -- Message bus dispatch trait (if exists)

### Phase 3 Context
- `.planning/phases/03-page-content-management/03-CONTEXT.md` -- Page tool decisions, block tool patterns, HandleTrait notes

### Project Context
- `.planning/PROJECT.md` -- Vision, constraints, key decisions
- `.planning/REQUIREMENTS.md` SS Articles (ARTC-01-05), SS Taxonomy (TAXO-01-06), SS Media (MDIA-01-03), SS Read-Only Entities (READ-01-03)

### Pitfalls
- `.planning/research/PITFALLS.md` -- MCP SDK pre-1.0 issues, transport pitfalls

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `src/Tool/Page*.php` -- Complete page tool suite (6 tools) to use as templates for article tools
- `src/Tool/Block*.php` -- Block manipulation tools (3 tools) to replicate for article blocks
- `src/Tool/HandleTrait.php` -- Message bus dispatch trait reusable for article message dispatch
- `src/Validator/WebspaceLocaleValidator.php` -- Webspace/locale validation, reusable across all new tools
- `src/Resource/BlocksResource.php` -- Block type discovery already covers articles (global resource)
- `src/Resource/TemplatesResource.php` -- Template discovery, may need extension for article templates

### Established Patterns
- `#[McpTool]` attribute with constructor injection of Sulu services
- `HandleTrait` for message bus dispatch with `EnableFlushStamp`
- `ContentManagerInterface` for content resolution and normalization
- `WebspaceLocaleValidator` for webspace/locale parameter validation
- Flat `src/Tool/` directory with entity-prefix naming (e.g., `PageGetTool`, `PageCreateTool`)
- `sulu_` prefix for MCP tool names (e.g., `sulu_page_get`, `sulu_article_get`)
- `blockProperty` parameter for block tools targeting specific content properties

### Integration Points
- New article tools: `src/Tool/ArticleGetTool.php`, `ArticleListTool.php`, `ArticleCreateTool.php`, `ArticleUpdateTool.php`, `ArticleDeleteTool.php`, `ArticlePublishTool.php`, `ArticleUnpublishTool.php`
- New article block tools: `src/Tool/ArticleBlockAddTool.php`, `ArticleBlockRemoveTool.php`, `ArticleBlockReorderTool.php`
- New taxonomy tools: `src/Tool/TagCreateTool.php`, `TagListTool.php`, `TagDeleteTool.php`, `CategoryCreateTool.php`, `CategoryListTool.php`, `CategoryDeleteTool.php`
- New media tools: `src/Tool/MediaListTool.php`, `MediaGetTool.php`, `MediaUpdateTool.php`
- New read-only tools: `src/Tool/SnippetGetTool.php`, `SnippetListTool.php`, `ContactListTool.php`, `NavigationGetTool.php`
- Sulu services to inject: `ArticleRepositoryInterface`, `TagManagerInterface`, `CategoryManagerInterface`, `MediaManagerInterface`, contact/account repositories, navigation service

</code_context>

<specifics>
## Specific Ideas

- Article tools should feel identical to page tools from the AI client's perspective -- same parameter patterns, same response structure. The AI should be able to "just know" how articles work if it knows how pages work
- Taxonomy tools are simple CRUD -- keep them minimal. Tags are flat key-value, categories are hierarchical
- Media tools are read + metadata update only in v1 -- no upload capability. AI can reference existing media when creating pages/articles
- Read-only tools provide AI with awareness of the full CMS landscape (contacts for author info, snippets for reusable content, navigation for site structure)

</specifics>

<deferred>
## Deferred Ideas

- Media upload from URL -- v2 (EXTD-02)
- Snippet CRUD (create, update, delete) -- v2 (EXTD-01)
- Navigation write operations -- v2 (EXTD-03)
- Article move/copy/locale copy operations -- future (CopyLocaleArticleMessage exists)
- Article version restore -- future (RestoreArticleVersionMessage exists)
- Locale-aware translation suggestions -- v2 (EXTD-04)

</deferred>

---

*Phase: 04-extended-content-tools*
*Context gathered: 2026-03-31*
