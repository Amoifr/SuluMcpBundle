# Project Research Summary

**Project:** Sulu MCP Server
**Domain:** CMS MCP Server (Symfony Bundle for Sulu CMS 3.x)
**Researched:** 2026-03-29
**Confidence:** HIGH

## Executive Summary

The Sulu MCP Server is a Symfony bundle that exposes Sulu CMS 3.x content management operations as MCP (Model Context Protocol) tools, enabling AI assistants to create, edit, and publish content while respecting user permissions and multi-webspace architecture. The recommended approach uses the official `symfony/mcp-bundle` (v0.6) wrapping `mcp/sdk` (v0.4) -- the canonical PHP MCP implementation maintained by Symfony, PHP Foundation, and Anthropic. This is layered on top of Sulu 3.0's new message-bus-based content architecture (Doctrine ORM replacing PHPCR), with a four-layer design: Transport/Auth, MCP Protocol, Application Services, and Sulu Integration. No alternative SDK or transport should be considered.

The primary differentiator against competing CMS MCP servers (Sanity, Contentful, Drupal, dotCMS) is a structured content guidelines system -- no competitor exposes editorial tone, audience, style, and brand rules as first-class MCP resources, and none auto-generate system prompts from CMS context. The recommended feature set targets approximately 15 tools (outcome-oriented, not 1:1 REST mapping) plus 8-9 MCP resources and 2-3 MCP prompts, with content guidelines shipping in v1 to establish unique market position.

The key risks are: (1) PHP-FPM worker pool exhaustion from long-lived connections -- mitigated by using Streamable HTTP transport instead of persistent SSE; (2) permission bypass through shared admin context -- mitigated by enforcing Sulu SecurityChecker on every tool call from day one; (3) pre-1.0 SDK instability -- mitigated by pinning versions and wrapping SDK usage behind internal interfaces; and (4) tool input injection via unsanitized content parameters -- mitigated by strict JSON Schema validation on all inputs. All four of these must be addressed in Phase 1 (Foundation), not bolted on later.

## Key Findings

### Recommended Stack

The stack is fully determined by the intersection of Sulu 3.0 and the official MCP ecosystem. Sulu 3.0 requires PHP 8.2+ and supports Symfony 6.4-7.4, but `symfony/mcp-bundle` requires Symfony 7.3+, narrowing the effective requirement to PHP 8.2+ / Symfony 7.3+. Both MCP packages are pre-1.0 (experimental) but are the only viable choice for Symfony projects.

**Core technologies:**
- **PHP 8.2+ / Symfony 7.3+**: Runtime and framework -- Sulu 3.0 minimum intersected with MCP bundle requirement
- **symfony/mcp-bundle ^0.6 + mcp/sdk ^0.4**: Official MCP integration -- attribute-based auto-discovery, Streamable HTTP transport, profiler integration
- **Sulu CMS 3.0**: Target platform -- new Doctrine-based content storage, message bus architecture (`CreatePageMessage`, `ModifyPageMessage`, etc.), hexagonal architecture in `/packages/`
- **Sulu services directly (not REST API)**: ContentManager, MediaManagerInterface, WebspaceManagerInterface, SecurityChecker -- injected via DI, no HTTP indirection

**Critical version constraint:** Projects on Symfony 6.4 LTS cannot use `symfony/mcp-bundle` without upgrading to 7.3+. This must be documented as a hard project requirement.

### Expected Features

**Must have (table stakes) -- ship in v1.0:**
- Page CRUD with template selection, webspace/locale context, cursor-based pagination
- Article CRUD following the same patterns
- Block management (add, remove, reorder) with dynamic block type discovery
- Publish/unpublish for pages and articles
- Tag and category CRUD
- Media list/search/get (reference existing assets)
- MCP resources: templates, block types, webspace config, sitemap/content tree
- Sulu user authentication with permission-scoped operations
- Content guidelines as MCP resource (primary differentiator, ship in v1)

**Should have (v1.x, after validation):**
- Snippet CRUD
- Media upload from URL
- Auto-generated system prompt (capstone feature combining all resources)
- Exportable prompt document for non-MCP AI clients
- Content validation feedback with field-level errors
- Template-aware enriched creation (pre-load template schema into creation context)

**Defer (v2+):**
- Company/business context resource (users can embed in guidelines for now)
- Sulu admin UI for guidelines management
- Locale-aware translation suggestions
- MCP gateway compatibility documentation
- Bulk publish operations

### Architecture Approach

A four-layer Symfony bundle architecture with clear boundaries: Layer 1 (Transport/Auth) handles Streamable HTTP and Symfony firewall-based Bearer token authentication mapped to Sulu users; Layer 2 (MCP Protocol) uses `symfony/mcp-bundle` for auto-discovered tools/resources/prompts via PHP 8 attributes; Layer 3 (Application Services) contains tool handlers, resource providers, permission guard, content guidelines manager, and block type discovery; Layer 4 (Sulu Services) consumes existing Sulu services unmodified via dependency injection.

**Major components:**
1. **Tool Handlers** (per domain: Page, Article, Block, Media, Tag, Category) -- execute CMS operations with permission-first pattern
2. **Resource Providers** (templates, block types, webspace config, sitemap, guidelines) -- expose read-only context data via `sulu://` URI namespace
3. **Permission Guard** -- wraps Sulu SecurityChecker, enforces per-operation, per-locale, object-level permission checks before any service call
4. **Content Guidelines Manager** -- Doctrine entity with global-defaults-plus-webspace-overrides resolution, structured schema (tone, audience, style, brand rules)
5. **Block Type Discovery** -- runtime introspection of Sulu XML templates via StructureFactory, cached with file-mtime invalidation
6. **MCP Token Authenticator** -- custom Symfony authenticator mapping Bearer tokens to Sulu users, per-request resolution (no session-scoped auth state)

### Critical Pitfalls

1. **PHP-FPM worker pool exhaustion** -- SSE connections hold workers indefinitely. Use Streamable HTTP as primary transport. If SSE needed, isolate to a separate FPM pool. Must address in Phase 1.
2. **Permission bypass through shared admin context** -- Every tool call must check Sulu SecurityChecker with the authenticated user's token. Implement PermissionGuard as base infrastructure, not per-tool. Must address in Phase 1.
3. **Tool input injection** -- Content parameters can carry indirect prompt injection. Validate all inputs against JSON Schema with strict typing and enum allowlists. Must address in Phase 1.
4. **MCP session state loss on PHP-FPM worker recycling** -- Use persistent session store (Redis or file-based) from day one, never in-memory. Must address in Phase 1.
5. **Block type schema drift after deployments** -- Cache block type schemas with file-mtime invalidation. Return actionable error messages when types are not found. Must address in Phase 3 (Block Operations).

## Implications for Roadmap

Based on combined research findings, dependency analysis, and pitfall-phase mapping, the recommended structure is 6 phases:

### Phase 1: Bundle Foundation and Transport
**Rationale:** Everything depends on the bundle skeleton, Streamable HTTP transport, authentication, and the base security infrastructure. Four of eight critical pitfalls must be addressed here. No MCP tool can be tested without this.
**Delivers:** Working Symfony bundle with MCP endpoint responding to JSON-RPC, authenticated Sulu user context on every request, PermissionGuard infrastructure, input validation framework.
**Addresses:** HTTP/SSE transport, Sulu user authentication, permission-scoped operations (FEATURES.md P1).
**Avoids:** FPM worker exhaustion (Pitfall 1), permission bypass (Pitfall 4), tool input injection (Pitfall 3), session state loss (Pitfall 6), transport deprecation (Pitfall 8).

### Phase 2: MCP Resources and Context Discovery
**Rationale:** AI clients need context before they can perform useful content operations. Page CRUD requires template knowledge, block operations require block type discovery. Resources have no dependencies on tools but tools depend on resources.
**Delivers:** MCP resources for templates, block types, webspace config, sitemap/content tree, navigation. Block type discovery service with cached introspection.
**Addresses:** Template discovery, block type discovery, webspace config, sitemap, navigation resources (FEATURES.md P1).
**Avoids:** Block schema drift (Pitfall 5) by building file-mtime invalidation into the discovery service from the start.

### Phase 3: Page CRUD and Publishing
**Rationale:** Pages are the most fundamental Sulu content type and validate the full architecture stack (auth -> MCP -> permission guard -> Sulu message bus -> response). Build read operations first for minimal risk, then writes.
**Delivers:** Page get/list/create/update/delete tools, publish/unpublish tool, block management tool (add/remove/reorder). All with webspace+locale scoping and permission enforcement.
**Addresses:** Page CRUD, block management, publish/unpublish (FEATURES.md P1).
**Avoids:** Document Manager semantics pitfall (Pitfall 2) by wrapping Sulu's message bus (not DocumentManager) in service layer.

### Phase 4: Content Guidelines and Prompts
**Rationale:** The content guidelines system is the primary differentiator. It depends on the guidelines Doctrine entity and service (which can be built early) plus all MCP resources from Phase 2 being stable. Shipping this in v1 establishes unique market position against Sanity, Contentful, and others.
**Delivers:** ContentGuideline Doctrine entity, guidelines manager with override resolution, MCP resource at `sulu://guidelines/{webspace}`, content guidelines MCP prompt.
**Addresses:** Content guidelines resource, per-webspace overrides (FEATURES.md P1 differentiator).
**Avoids:** Guideline token overflow (Pitfall 7) by enforcing structured schema with field-level character limits and token budget from the start.

### Phase 5: Remaining Content Tools
**Rationale:** Once page patterns are proven, article/snippet/media/tag/category tools follow established patterns with minimal architectural risk. These are relatively independent and can be built in parallel.
**Delivers:** Article CRUD + publishing, snippet CRUD, media list/search/get, tag CRUD, category CRUD.
**Addresses:** Article CRUD, tag/category CRUD, media management, snippet CRUD (FEATURES.md P1 and P2).
**Avoids:** No new pitfalls -- patterns established in Phase 3 are reused.

### Phase 6: Advanced Features
**Rationale:** Capstone features that combine multiple components. Auto-generated system prompt requires all resources and guidelines to be mature. Template-aware creation requires proven page creation patterns. These add polish and differentiation.
**Delivers:** Auto-generated system prompt, exportable prompt document, content validation feedback, template-aware enriched creation, media upload from URL.
**Addresses:** Auto-generated system prompt, exportable prompt, validation feedback, template-aware creation, media upload (FEATURES.md P2).
**Avoids:** No new pitfalls -- builds on stable foundation.

### Phase Ordering Rationale

- **Phase 1 before everything**: Transport and auth are prerequisites for any MCP interaction. Four critical pitfalls must be addressed here, and retrofitting them later means touching every tool handler.
- **Phase 2 before Phase 3**: FEATURES.md dependency analysis shows Page CRUD requires template discovery and webspace config. AI cannot create pages without knowing available templates. Resources must exist before tools are useful.
- **Phase 3 before Phase 5**: Pages validate the full stack. Article/snippet/media tools reuse page patterns -- building them first without a proven pattern risks inconsistency.
- **Phase 4 alongside or after Phase 3**: Content guidelines enhance all content tools but do not block them. The Doctrine entity can be built in Phase 1 (no dependencies), but the MCP resource and prompt integration depends on MCP being functional (Phase 2+).
- **Phase 5 after Phase 3**: Extending proven patterns to new content types is low-risk and can be parallelized.
- **Phase 6 last**: Capstone features depend on all previous phases being stable and mature.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 1 (Transport/Auth):** MCP SDK session store configuration options and Symfony firewall integration for the MCP endpoint need validation. The `symfony/mcp-bundle` is pre-1.0 and its configuration API may change. Test with actual AI clients (Claude.ai, at minimum) early.
- **Phase 2 (Resources):** Sulu 3.0's `ContentMetadataInspector` and `StructureFactory` APIs for runtime template/block introspection need validation against actual Sulu 3.0 source. The 3.0 documentation is incomplete and the namespace refactoring (`/packages/` vs `/src/Sulu/Bundle/`) means some service names may differ from what was researched.
- **Phase 3 (Page CRUD):** The exact message bus dispatch pattern for Sulu 3.0 pages (`CreatePageMessage` with `EnableFlushStamp` via `MessageBusInterface`) should be validated against a running Sulu 3.0 instance. The message signatures may have changed post-research.

Phases with standard patterns (likely skip research-phase):
- **Phase 4 (Guidelines):** Standard Doctrine entity + service pattern. Well-understood.
- **Phase 5 (Remaining Tools):** Extends established patterns from Phase 3. Standard CRUD operations against well-documented Sulu services (MediaManagerInterface, TagManagerInterface, etc.).
- **Phase 6 (Advanced Features):** Composition of existing components. No new architectural patterns needed.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Official SDK verified on Packagist/GitHub, Sulu 3.0 architecture verified via source. Version constraints confirmed. Only risk is pre-1.0 API changes. |
| Features | HIGH | Competitor analysis covers 5 CMS MCP servers. Feature landscape well-mapped. Differentiator (content guidelines) validated against all competitors. |
| Architecture | HIGH | Four-layer design follows standard Symfony bundle patterns. MCP protocol primitives well-documented. Sulu service integration verified via 3.0 source code. |
| Pitfalls | HIGH | Multi-source: OWASP MCP Top 10, academic taxonomy of 419 MCP faults, official spec, Sulu docs, PHP SSE production experience. Pitfall-to-phase mapping is concrete. |

**Overall confidence:** HIGH

### Gaps to Address

- **Sulu 3.0 runtime validation:** Research was conducted against Sulu 3.0 GitHub source, not a running instance. Message bus dispatch patterns, service names in the DI container, and `StructureFactory` introspection APIs should be validated against an actual Sulu 3.0 project in Phase 1.
- **MCP SDK resource template support:** Resource templates (`#[McpResourceTemplate]`) are defined in the spec but flagged as not yet functional in mcp/sdk issue #9. This affects parameterized resources like `sulu://templates/{webspace}`. Fallback: use regular `#[McpResource]` with a single endpoint returning all data, or poll the SDK repo for the fix.
- **Pre-1.0 SDK stability:** Both `mcp/sdk` (v0.4) and `symfony/mcp-bundle` (v0.6) may change APIs before 1.0. Pin exact versions in `composer.json`. Wrap all SDK interactions behind internal interfaces so refactoring is localized.
- **Sulu 3.0 DocumentManager vs Message Bus:** PITFALLS.md references DocumentManager persist/flush semantics, but STACK.md confirms Sulu 3.0 replaced DocumentManager with a message bus for pages/articles/snippets. Verify that the message bus pattern is the only path needed and that DocumentManager pitfalls apply only to media/tag/category operations that may still use traditional Doctrine patterns.
- **OAuth 2.1 future requirement:** The architecture uses Bearer token auth (not full OAuth 2.1). If MCP clients begin requiring OAuth discovery metadata, the auth layer will need extension. The architecture supports this without rearchitecting -- OAuth metadata endpoints can be added alongside existing token validation.

## Sources

### Primary (HIGH confidence)
- [Official MCP PHP SDK (v0.4)](https://github.com/modelcontextprotocol/php-sdk) -- SDK architecture, transport, session handling
- [symfony/mcp-bundle (v0.6)](https://packagist.org/packages/symfony/mcp-bundle) -- Bundle configuration, attribute discovery, Symfony integration
- [MCP Specification 2025-03-26 / 2025-06-18](https://modelcontextprotocol.io/specification/2025-06-18/basic/transports) -- Streamable HTTP transport, session management, protocol primitives
- [Sulu 3.0 source code (GitHub)](https://github.com/sulu/sulu/tree/3.0) -- Message classes, service interfaces, content architecture
- [OWASP MCP Top 10](https://owasp.org/www-project-mcp-top-10/) -- MCP-specific security vulnerabilities and mitigations
- [Sanity MCP Server](https://www.sanity.io/docs/ai/mcp-server), [Contentful MCP Server](https://github.com/contentful/contentful-mcp-server), [Drupal MCP Server](https://www.drupal.org/project/mcp_server), [dotCMS MCP Blog](https://www.dotcms.com/blog/meet-the-mcp-server) -- Competitor feature analysis

### Secondary (MEDIUM confidence)
- [MCP Academic Fault Taxonomy (2026)](https://arxiv.org/html/2603.05637v1) -- 419 fault reports across 5 categories
- [MCP Best Practices (Workato, Steipete)](https://docs.workato.com/mcp/mcp-server-design.html) -- Tool design patterns (5-8 tools per server)
- [Sulu 3.0 UPGRADE-3.x.md](https://github.com/sulu/sulu/blob/3.0/UPGRADE-3.x.md) -- Namespace changes, removed services
- [PHP SSE Production Pitfalls](https://kevinchoppin.dev/blog/server-sent-events-in-php) -- FPM worker exhaustion, session locks

### Tertiary (LOW confidence)
- [MCP SDK Resource Template issue #9](https://github.com/modelcontextprotocol/php-sdk/issues/9) -- Resource templates not yet functional (needs revalidation)
- [Symfony MCP SDK experimental status](https://github.com/symfony/mcp-sdk) -- Pre-1.0 API stability concerns

---
*Research completed: 2026-03-29*
*Ready for roadmap: yes*
