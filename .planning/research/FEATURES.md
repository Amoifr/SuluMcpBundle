# Feature Research

**Domain:** CMS MCP Server (Sulu CMS 3.x Symfony Bundle)
**Researched:** 2026-03-29
**Confidence:** HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

Features every CMS MCP server provides. Missing any of these means the product cannot compete with Sanity, Contentful, Drupal, or dotCMS MCP implementations.

#### Content CRUD Operations

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Page read/get (single + list) | Core CMS operation; every CMS MCP server exposes this | MEDIUM | Must support filtering by webspace, locale, template. Cursor-based pagination for lists. |
| Page create | Contentful, Sanity, Drupal all expose document creation | MEDIUM | Requires template selection, webspace/locale context. Create as draft by default. |
| Page update/patch | Editing existing content is fundamental | MEDIUM | Partial updates preferred over full replacement. Sanity uses patch semantics, Contentful uses modify. |
| Page delete | Standard CRUD completion | LOW | Soft-delete or move to trash preferred. Must check permissions. |
| Article CRUD (get, create, update, delete) | Articles are a first-class Sulu content type alongside pages | MEDIUM | Same patterns as pages but through ArticleBundle services. |
| Block management (add, remove, reorder) | Sulu's block system is its core content modeling approach; AI must manipulate blocks | HIGH | Dynamic block type discovery required. Must validate block type exists before adding. Most complex table-stakes feature. |
| Snippet CRUD | Reusable content fragments are standard across CMS platforms | MEDIUM | Snippets are shared across webspaces; locale handling important. |

#### Publishing & Workflow

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Publish page/article | Every CMS MCP server (Sanity, Contentful, dotCMS) exposes publish | LOW | Transitions draft to live. Must respect user permissions. |
| Unpublish page/article | Contentful and Sanity both expose unpublish tools | LOW | Reverts to draft state. |
| Get publish status | Knowing draft vs published state is essential for AI decision-making | LOW | Return as part of content responses, not a separate tool. |

#### Taxonomy & Organization

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Tag CRUD (create, list, delete) | Tags are universal CMS organizing primitives; Contentful, Sanity expose these | LOW | Simple operations against Sulu tag system. |
| Category CRUD (create, list, delete) | Categories provide hierarchical taxonomy; table stakes for content organization | LOW | Must expose category tree structure. |
| Assign tags/categories to content | Connecting taxonomy to content completes the feature | LOW | Part of content update operations, not separate tools. |

#### Media Management

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| List/search media | All CMS MCP servers expose media browsing | MEDIUM | Pagination essential. Filter by collection, type, tags. |
| Get media details | Retrieve metadata, URLs, dimensions for a specific asset | LOW | Return CDN URLs, alt text, copyright info. |
| Upload media from URL | MCP protocol limits direct binary uploads; URL-based upload is the standard pattern | MEDIUM | Download from URL server-side, create in Sulu media system. Base64 as fallback for small files. |
| Update media metadata | Editing alt text, titles, copyright is needed for accessibility and SEO | LOW | Partial update on media entity fields. |

#### Schema & Context Discovery

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| List available templates | AI must know what page templates exist to create pages correctly | LOW | MCP resource, not a tool. Expose as `sulu://templates/{webspace}`. |
| List available block types | Dynamic block discovery is critical; AI cannot add blocks it does not know about | MEDIUM | MCP resource. Include field schemas per block type so AI knows what data each block needs. |
| Get webspace configuration | Multi-webspace context is essential for correct content targeting | LOW | MCP resource. Expose available webspaces with their locales, URLs, names. |
| Get sitemap/content tree | Understanding page hierarchy is needed for navigation context and creating pages at correct positions | MEDIUM | MCP resource. Tree structure of pages per webspace/locale. |
| Get navigation structures | Navigation context informs where content appears on site | LOW | MCP resource. Expose navigation layers with their items. |

#### Authentication & Authorization

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Sulu user authentication | Every enterprise CMS MCP server (dotCMS, Drupal) enforces user-level auth | MEDIUM | HTTP/SSE with token-based auth mapped to Sulu user. All operations scoped to user permissions. |
| Permission-scoped operations | dotCMS explicitly markets governance; Drupal uses OAuth 2.1 | LOW | Not a separate feature but a cross-cutting concern. Every tool call checks user permissions via Sulu's security system. |
| Webspace + locale per call | Multi-site CMS MCP servers (dotCMS, Contentful) scope operations to site/space | LOW | Required parameters on content tools. Validated against user's webspace permissions. |

### Differentiators (Competitive Advantage)

Features that set this Sulu MCP server apart. These do not exist in competing CMS MCP implementations or are done significantly better here.

#### Content Guidelines System (Primary Differentiator)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Content guidelines as MCP resource | No other CMS MCP server exposes structured editorial guidelines (tone, audience, style, brand rules) as first-class context. Sanity has a freeform instructions field; this is structured and hierarchical. | MEDIUM | MCP resource at `sulu://guidelines/{webspace}`. Includes tone, target audience, writing style, brand vocabulary, content rules. Global defaults with per-webspace overrides. |
| Auto-generated system prompt | Unique: no competitor auto-generates a comprehensive system prompt from CMS context. Claude.ai and ChatGPT project setup becomes copy-paste. | MEDIUM | Combines guidelines + templates + block types + webspace config + company context into a single instruction document. Exposed as MCP resource and downloadable file. |
| Per-webspace guideline overrides | Multi-brand installations need different voices per site. dotCMS supports multi-tenant but has no guideline concept. | LOW | Override resolution: webspace-specific -> global default. Merge strategy for partial overrides. |
| Company/business context resource | Providing company name, industry, product descriptions, key messaging gives AI writers essential brand context beyond tone rules. | LOW | MCP resource at `sulu://context/company`. Static configuration, exposed as structured data. |

#### AI-Aware Content Creation

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Template-aware page creation | When AI creates a page, it receives the full template schema (required fields, block zones, property types) so it can populate all fields correctly in one call. No other CMS MCP server pre-loads template structure into creation context. | MEDIUM | Tool enriches creation request with template metadata. Reduces back-and-forth between AI and server. |
| Block type schema in creation context | When adding blocks, AI receives the exact field schema for that block type (text fields, media references, select options). Eliminates trial-and-error. | MEDIUM | Part of block type resource. Each block type includes its full field definition with types, constraints, and defaults. |
| Content validation feedback | Return structured validation errors (which field, what rule, what's wrong) so AI can self-correct. Most CMS MCP servers return opaque errors. | MEDIUM | Map Sulu form validation errors to structured MCP error responses with field-level detail. |

#### Intelligent Context Layer

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Exportable prompt for manual AI setup | For AI clients without MCP support, provide a downloadable/copyable prompt that includes all context. No competitor does this. | LOW | Generate markdown document from same data as auto-generated system prompt. Endpoint or admin action. |
| MCP gateway compatibility | ChatGPT uses MCP via gateway bridges. Explicit compatibility testing and documentation for this path. | LOW | HTTP/SSE transport already enables this. Document configuration for popular gateways. |
| Locale-aware content suggestions | AI can request "what locales does this content exist in" and "what's missing" to guide translation workflows. | LOW | Part of content get responses. Include locale availability matrix. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem appealing but create more problems than they solve.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Direct AI content auto-publishing | "Let AI write and publish without human review" | Removes editorial oversight. One hallucination goes live. Every enterprise CMS (dotCMS) explicitly markets governance for this reason. | AI creates as draft. Human reviews and publishes. Publishing tool exists but should be a deliberate separate step, not automatic. |
| Full REST API proxy | "Expose all Sulu REST endpoints via MCP" | Turns MCP server into a generic API wrapper. Violates MCP best practice of outcome-oriented tools (5-8 per server). Creates tool sprawl that confuses AI agents. | Curate specific, well-designed tools around content workflows. Use Sulu services directly, not API passthrough. |
| Real-time collaborative editing | "AI and human edit same page simultaneously" | Requires OT/CRDT conflict resolution, WebSocket infrastructure, massive complexity. No CMS MCP server does this. | Sequential editing. AI creates/edits draft, human reviews. Concurrency locking deferred (per PROJECT.md out-of-scope). |
| Custom block type creation via AI | "Let AI define new block types" | Block types define site architecture; creating them requires template changes, Twig files, developer workflow. Not a content editor operation. | AI discovers and uses existing block types. Block creation remains a developer task. Already in PROJECT.md out-of-scope. |
| AI-generated media (image generation) | "Generate images for content on the fly" | Requires external AI service integration, costs money per call, quality/brand consistency issues. Sanity does this but it's a separate product feature, not core MCP. | AI can reference existing media from the media library. Image generation is a separate concern from CMS content management. |
| Sulu admin UI for guidelines | "Build a full admin panel for managing guidelines" | Significant frontend development effort for v1. Diverts focus from core MCP functionality. | Store guidelines in YAML config or database with simple structure. Admin UI deferred to v2 (per PROJECT.md). |
| Undo/revision history browsing | "Let AI browse and revert to previous page versions" | Complex to expose meaningfully. AI does not need version history to create/edit content. Version management is a human editorial concern. | Sulu already tracks versions. Humans can revert in admin UI. AI works with current state. |
| Bulk operations across all content | "Update all pages matching X criteria" | Dangerous with AI agents. One bad prompt updates hundreds of pages. Rate limiting and scope constraints are hard to enforce perfectly. | Individual content operations only. Batch publish (like Contentful) limited to explicit lists, not query-based mass updates. |
| Content workflow states beyond draft/published | "Add review, approval, scheduled states" | Workflow engines are complex. Sulu's core is draft/published. Adding states requires custom workflow definition and UI. | Stick with Sulu's native draft/published model. Workflow extensions are a future concern if demand materializes. |

## Feature Dependencies

```
[Sulu User Auth]
    └──requires──> [HTTP/SSE Transport]

[Page CRUD]
    └──requires──> [Template Discovery (resource)]
    └──requires──> [Webspace Config (resource)]
    └──requires──> [Auth]

[Article CRUD]
    └──requires──> [Auth]
    └──requires──> [Webspace Config (resource)]

[Block Management]
    └──requires──> [Block Type Discovery (resource)]
    └──requires──> [Page CRUD] or [Article CRUD]

[Content Guidelines (resource)]
    └──enhances──> [Page CRUD]
    └──enhances──> [Article CRUD]
    └──enhances──> [Snippet CRUD]

[Auto-Generated System Prompt]
    └──requires──> [Content Guidelines (resource)]
    └──requires──> [Template Discovery (resource)]
    └──requires──> [Block Type Discovery (resource)]
    └──requires──> [Webspace Config (resource)]
    └──requires──> [Company Context (resource)]

[Template-Aware Page Creation]
    └──requires──> [Template Discovery (resource)]
    └──requires──> [Page CRUD]

[Publishing]
    └──requires──> [Page CRUD] or [Article CRUD]

[Tag CRUD] ──independent──
[Category CRUD] ──independent──
[Media Management] ──independent──

[Navigation (resource)]
    └──requires──> [Sitemap/Content Tree (resource)]

[Exportable Prompt]
    └──requires──> [Auto-Generated System Prompt]
```

### Dependency Notes

- **Block Management requires Block Type Discovery:** AI cannot add blocks without knowing what block types are available and what fields they contain. The resource must exist before the tool is useful.
- **Auto-Generated System Prompt requires all resources:** This is the capstone feature that combines all context resources into one coherent instruction document. Build resources first.
- **Page CRUD requires Template Discovery:** Pages in Sulu are template-bound. Creating a page without knowing available templates produces errors.
- **Tag/Category/Media are independent:** These can be built in any order and have no dependencies on other content features.
- **Content Guidelines enhance all content tools:** Guidelines do not block content CRUD but make the AI output dramatically better. Ship CRUD first, guidelines second.

## MVP Definition

### Launch With (v1.0)

Minimum viable product: AI can create and manage Sulu content with full context awareness.

- [ ] **HTTP/SSE Transport** -- Foundation for all communication; nothing works without it
- [ ] **Sulu User Authentication** -- Security prerequisite; cannot expose content operations without auth
- [ ] **MCP Resources: templates, block types, webspace config, sitemap** -- Context the AI needs before it can do anything useful
- [ ] **Page CRUD (get, list, create, update, delete)** -- Primary content type; validates the entire architecture
- [ ] **Block management (add, remove, reorder on pages)** -- Sulu's defining content model; must work for the product to matter
- [ ] **Publishing (publish/unpublish pages)** -- Completes the content lifecycle
- [ ] **Article CRUD + publishing** -- Second core content type; extends patterns from pages
- [ ] **Tag and Category CRUD** -- Simple operations that round out content organization
- [ ] **Media list/get/search** -- AI needs to reference existing media when building content
- [ ] **Content guidelines as MCP resource** -- Primary differentiator; ship with v1 to establish unique value
- [ ] **Webspace + locale scoping on all tools** -- Multi-site is table stakes for Sulu users

### Add After Validation (v1.x)

Features to add once core content management is proven and users provide feedback.

- [ ] **Snippet CRUD** -- Add when users request reusable content management; low complexity extension of page patterns
- [ ] **Media upload (from URL)** -- Add when content creation workflows need new assets, not just referencing existing ones
- [ ] **Auto-generated system prompt** -- Add once all resources are stable and tested; requires all context resources to be mature
- [ ] **Exportable prompt document** -- Add alongside auto-generated system prompt; same data, different format
- [ ] **Navigation management** -- Add when users need AI to modify nav structures, not just read them
- [ ] **Content validation feedback** -- Add when error reports from AI usage reveal that opaque errors are causing problems
- [ ] **Template-aware enriched creation** -- Add when page creation tool proves too many round-trips without template pre-loading

### Future Consideration (v2+)

Features to defer until product-market fit is established.

- [ ] **Company/business context resource** -- Valuable but not blocking; users can include company context in guidelines for now
- [ ] **Locale-aware translation suggestions** -- Requires established multi-locale usage patterns to design well
- [ ] **MCP gateway compatibility docs/testing** -- Defer until ChatGPT MCP gateway ecosystem stabilizes
- [ ] **Sulu admin UI for guidelines** -- Only build when config-file-based guidelines prove insufficient
- [ ] **Bulk publish operations** -- Only if users demonstrate safe patterns for batch operations

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| HTTP/SSE Transport + Auth | HIGH | HIGH | P1 |
| MCP Resources (templates, blocks, webspace, sitemap) | HIGH | MEDIUM | P1 |
| Page CRUD | HIGH | MEDIUM | P1 |
| Block Management | HIGH | HIGH | P1 |
| Publish/Unpublish | HIGH | LOW | P1 |
| Article CRUD | HIGH | MEDIUM | P1 |
| Content Guidelines Resource | HIGH | MEDIUM | P1 |
| Tag/Category CRUD | MEDIUM | LOW | P1 |
| Media List/Get/Search | MEDIUM | MEDIUM | P1 |
| Snippet CRUD | MEDIUM | MEDIUM | P2 |
| Media Upload (URL) | MEDIUM | MEDIUM | P2 |
| Auto-Generated System Prompt | HIGH | MEDIUM | P2 |
| Exportable Prompt Document | MEDIUM | LOW | P2 |
| Content Validation Feedback | MEDIUM | MEDIUM | P2 |
| Navigation Management | LOW | MEDIUM | P2 |
| Template-Aware Enriched Creation | MEDIUM | MEDIUM | P2 |
| Company Context Resource | LOW | LOW | P3 |
| Locale Translation Suggestions | LOW | MEDIUM | P3 |
| Gateway Compatibility | LOW | LOW | P3 |
| Admin UI for Guidelines | MEDIUM | HIGH | P3 |

**Priority key:**
- P1: Must have for launch
- P2: Should have, add when possible
- P3: Nice to have, future consideration

## Competitor Feature Analysis

| Feature | Sanity MCP | Contentful MCP | Drupal MCP | dotCMS MCP | Sulu MCP (Ours) |
|---------|-----------|----------------|------------|------------|-----------------|
| Content CRUD | Full (create, patch, query via GROQ) | Full (40+ tools, 9 categories) | Full (config-driven tool discovery) | Full (search, create, update) | Full (pages, articles, blocks, snippets) |
| Schema Discovery | get_schema, list_workspace_schemas | Content type tools (list, get) | Dynamic from config entities | Content type browsing | MCP resources (templates, block types) |
| Publishing | publish/unpublish documents | Bulk publish/unpublish | Via workflow tools | Workflow orchestration | Publish/unpublish per content item |
| Media | AI image generation + transform | Upload, list, modify, publish assets | Via entity management | Asset management | List, search, get, upload-from-URL |
| Multi-Site/Space | Single project scoped | Space + environment scoped | Single site | Multi-site, multi-tenant | Multi-webspace with per-call scoping |
| Localization | Per-document locale | Full locale CRUD + per-field | Entity translation | Multi-language | Locale parameter per tool call |
| Content Guidelines | Freeform instructions field in Agent Context | None | None | None | **Structured guidelines: tone, audience, style, brand. Global + per-webspace overrides.** |
| System Prompt Generation | None | None | None | None | **Auto-generated system prompt from CMS context** |
| Auth Model | API tokens | Personal access tokens, OAuth | OAuth 2.1 | User-based tokens with AI user roles | Sulu user auth (inherits permissions) |
| Tool Count | ~25 tools | ~40 tools | Config-driven (variable) | Unknown (enterprise) | Target: 15-20 tools (focused) |
| Governance | Basic | Admin controls tool categories per environment | Role-based, opt-in content types | Full governance marketing (audit, trace, reversible) | Permission-scoped via Sulu user system |

### Key Competitive Insights

1. **Sanity** is the most mature CMS MCP server with schema-aware tools and semantic search, but has no structured content guidelines concept -- only a freeform instructions field.
2. **Contentful** has the most tools (~40) but is arguably over-granular; MCP best practices recommend 5-8 tools per server.
3. **Drupal** takes a configuration-driven approach where tools are discovered from config entities, similar to our dynamic block discovery need.
4. **dotCMS** focuses on enterprise governance (audit trails, AI user roles) as its differentiator.
5. **Nobody** provides structured content guidelines as a first-class CMS concept or auto-generates system prompts from CMS context. This is our unique value proposition.

## MCP Primitive Design Recommendations

### Tools vs Resources vs Prompts

Based on MCP protocol semantics and CMS MCP server patterns:

**Tools (model-controlled, action-oriented):**
- Content CRUD operations (pages, articles, snippets, tags, categories)
- Block management (add, remove, reorder)
- Publishing (publish, unpublish)
- Media operations (list, search, get, upload)

**Resources (application-controlled, read-only context):**
- `sulu://templates/{webspace}` -- Available page templates with field schemas
- `sulu://blocks/{webspace}` -- Available block types with field definitions
- `sulu://webspaces` -- Webspace configuration (locales, URLs, names)
- `sulu://sitemap/{webspace}/{locale}` -- Content tree structure
- `sulu://navigation/{webspace}/{locale}` -- Navigation structures
- `sulu://guidelines/{webspace}` -- Content guidelines (tone, audience, style)
- `sulu://guidelines` -- Global default guidelines
- `sulu://context/company` -- Company/business context
- `sulu://prompt/system` -- Auto-generated system prompt

**Prompts (user-controlled, workflow templates):**
- `create-page` -- Guided page creation workflow with template selection
- `write-article` -- Article creation with guideline-aware content generation
- `content-audit` -- Review existing content against guidelines

### Tool Design Patterns

Following MCP best practices (5-8 tools per logical domain, outcome-oriented, not 1:1 REST mapping):

**Recommended tool organization (~15 tools):**

1. `sulu_get_page` -- Get a single page by ID with all content/blocks
2. `sulu_list_pages` -- List/search pages with cursor pagination
3. `sulu_create_page` -- Create a page (template, title, URL, blocks)
4. `sulu_update_page` -- Update page properties and content
5. `sulu_delete_page` -- Delete a page
6. `sulu_manage_blocks` -- Add, remove, reorder blocks on a page (combined operation)
7. `sulu_get_article` / `sulu_list_articles` -- Article read operations
8. `sulu_create_article` / `sulu_update_article` / `sulu_delete_article` -- Article write operations
9. `sulu_publish` -- Publish a page or article (polymorphic)
10. `sulu_unpublish` -- Unpublish a page or article
11. `sulu_manage_tags` -- Create, list, delete tags (combined)
12. `sulu_manage_categories` -- Create, list, delete categories (combined)
13. `sulu_search_media` -- List/search media with filters
14. `sulu_get_media` -- Get media details and URLs
15. `sulu_upload_media` -- Upload media from URL

**Design principles applied:**
- Combined `sulu_manage_blocks` instead of separate add/remove/reorder tools (outcome-oriented)
- Combined `sulu_manage_tags` and `sulu_manage_categories` (simple CRUD in one tool with action parameter)
- Separate get/list for pages and articles (different query patterns, different use cases)
- Polymorphic `sulu_publish` works for both pages and articles (same outcome, different content type)
- Every tool accepts `webspace` and `locale` parameters
- Cursor-based pagination on all list operations
- Structured error responses with field-level validation details

## Sources

- [Sanity MCP Server Docs](https://www.sanity.io/docs/ai/mcp-server) -- Official Sanity MCP documentation. HIGH confidence.
- [Sanity Agent Context](https://www.sanity.io/docs/ai/agent-context) -- Sanity's approach to AI context and instructions. HIGH confidence.
- [Contentful MCP Server (GitHub)](https://github.com/contentful/contentful-mcp-server) -- Official Contentful MCP server with ~40 tools. HIGH confidence.
- [dotCMS MCP Server Blog](https://www.dotcms.com/blog/meet-the-mcp-server) -- dotCMS enterprise governance-focused MCP. MEDIUM confidence.
- [Drupal MCP Server](https://www.drupal.org/project/mcp_server) -- Drupal configuration-driven MCP implementation. HIGH confidence.
- [Optimizely Discovery-First MCP](https://johnnymullaney.com/2025/10/13/building-a-discovery-first-mcp-for-optimizely-cms-part-1-of-4/) -- Discovery-first MCP pattern for CMS. MEDIUM confidence.
- [MCP Best Practices (Workato)](https://docs.workato.com/mcp/mcp-server-design.html) -- Tool design guidelines: 5-8 tools per server. MEDIUM confidence.
- [MCP Best Practices (Peter Steinberger)](https://steipete.me/posts/2025/mcp-best-practices) -- Practical MCP server patterns. MEDIUM confidence.
- [MCP Resources vs Tools (Medium)](https://medium.com/@laurentkubaski/mcp-resources-explained-and-how-they-differ-from-mcp-tools-096f9d15f767) -- When to use resources vs tools. MEDIUM confidence.
- [MCP Pagination Spec](https://modelcontextprotocol.io/specification/2025-03-26/server/utilities/pagination) -- Official cursor-based pagination. HIGH confidence.
- [MCP Architecture](https://modelcontextprotocol.io/docs/learn/architecture) -- Official MCP protocol architecture. HIGH confidence.
- [Sulu Blocks Guide](https://sulu.io/guides/structured-content-with-blocks) -- Sulu's block-based content model. HIGH confidence.
- [Sulu Templates Docs](https://docs.sulu.io/en/2.5/book/templates.html) -- Sulu template system. HIGH confidence.
- [Sulu Multiportal](https://sulu.io/platform/solutions/multiportal-and-multisite) -- Sulu multi-webspace architecture. HIGH confidence.
- [Symfony MCP SDK (GitHub)](https://github.com/symfony/mcp-sdk) -- Experimental Symfony MCP SDK. LOW confidence (experimental status).
- [PHP MCP Server (GitHub)](https://github.com/php-mcp/server) -- PHP MCP server implementation. MEDIUM confidence.

---
*Feature research for: CMS MCP Server (Sulu CMS 3.x)*
*Researched: 2026-03-29*
