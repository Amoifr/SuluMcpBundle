# Sulu MCP Server

## What This Is

A Symfony bundle for Sulu CMS 3.x that exposes content management operations as MCP (Model Context Protocol) tools via HTTP/SSE. It enables AI assistants like Claude.ai and ChatGPT to manage Sulu content directly — creating pages, adding blocks, publishing articles, managing media, and more — while respecting Sulu's user permissions and multi-webspace architecture.

## Core Value

AI assistants can create, edit, and publish content in Sulu CMS with full awareness of the project's content guidelines, templates, and brand context — writing on-brand content, not just executing CRUD.

## Current State (v1.0 shipped 2026-04-03)

- **44 MCP tools** covering pages, articles, blocks, taxonomy, media, snippets, contacts, navigation, publishing, guidelines
- **4,891 LOC** PHP source + **6,110 LOC** tests (245 tests, 748 assertions)
- **OAuth 2.0 authentication** mapping Sulu admin users to bearer tokens
- **Content guidelines system** with global defaults and per-webspace overrides
- **Deployed and tested** on stage.sulu.io with Claude.ai MCP integration

## Requirements

### Validated

- ✓ MCP Streamable HTTP transport — v1.0
- ✓ Symfony bundle with DI container access — v1.0
- ✓ PHP 8 attribute auto-discovery (#[McpTool]) — v1.0
- ✓ Sulu user OAuth authentication — v1.0
- ✓ Structured permission-denied errors — v1.0
- ✓ Webspace/locale parameters on all tools — v1.0
- ✓ Locale-appropriate data — v1.0
- ✓ Template, block type, webspace MCP resources — v1.0
- ✓ Content guidelines (global + per-webspace overrides) — v1.0
- ✓ Company context MCP resource — v1.0
- ✓ Page CRUD (get, list, tree, create, update, delete) — v1.0
- ✓ Article CRUD (get, list, create, update, delete) — v1.0
- ✓ Block operations (add, remove, reorder, update, list with pagination) — v1.0
- ✓ Publishing/unpublishing (pages + articles) — v1.0
- ✓ Tag CRUD — v1.0
- ✓ Category CRUD (tree structure) — v1.0
- ✓ Media list, get, update metadata — v1.0
- ✓ Snippet read — v1.0
- ✓ Contact/account read — v1.0
- ✓ Navigation read — v1.0
- ✓ Guideline generator MCP prompt — v1.0
- ✓ Preview link generation/revocation — v1.0

### Active

- [ ] Proactive permission guard (tools check permissions before dispatching)
- [ ] Sitemap MCP resource (dedicated resource, not just navigation)
- [ ] Webspace/locale pre-validation (clean errors for invalid params)
- [ ] Tag/category assignment documentation in tool descriptions
- [ ] Content Assistant prompt template (docs/CONTENT_ASSISTANT_PROMPT.md — drafted)
- [ ] README with installation and getting-started guide
- [ ] MCP gateway compatibility testing for ChatGPT

### Out of Scope

- REST API layer — bundle uses Sulu services directly, no API indirection
- Stdio transport — HTTP/SSE only (remote/cloud deployments are the target)
- Concurrency locking — not in v1, may revisit if concurrent AI sessions become an issue
- Custom block type creation — the bundle discovers existing block types, it doesn't create new ones
- Direct AI auto-publishing — safety concern: AI creates drafts, humans review and publish deliberately
- Snippet write operations — snippets are global shared content, managed by developers
- Media upload — Sulu media upload requires multipart, beyond typical MCP tool scope

## Context

- **Sulu 3.x**: Next major version of Sulu CMS. Bundle targets this version exclusively.
- **PHP 8.2+**: Minimum PHP version, aligned with Sulu 3.x requirements.
- **Symfony 7.3+**: Required by symfony/mcp-bundle.
- **MCP protocol**: Model Context Protocol — open standard for AI tool access. Claude.ai supports natively; ChatGPT via gateways.
- **Content guidelines**: Novel concept introduced by this bundle. Sulu has no native AI guidelines.
- **Multi-webspace**: All tools accept webspace/locale. Guidelines support per-webspace overrides.
- **Pre-1.0 SDK**: Both mcp/sdk (^0.4) and symfony/mcp-bundle (^0.6) are pre-1.0. API may change.

## Constraints

- **Tech Stack**: PHP 8.2+, Symfony bundle, Sulu 3.x — no external runtime dependencies
- **Auth**: Must use Sulu's native user authentication — no separate auth system
- **Transport**: HTTP/SSE only — designed for network-accessible deployments
- **Permissions**: All operations must respect the authenticated Sulu user's permissions — no privilege escalation

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Direct service usage over REST API | Avoids API layer overhead, leverages full Sulu service capabilities | ✓ Good — clean architecture, fast |
| Symfony bundle over standalone process | Access to DI container, services, and Sulu kernel | ✓ Good — seamless integration |
| HTTP/SSE over stdio | Needed for remote/cloud deployments | ✓ Good — works with Claude.ai, ngrok, stage |
| Dynamic block discovery | Projects configure custom blocks; hardcoding would limit adoption | ✓ Good — BlocksResource inspects metadata at runtime |
| Content guidelines as new concept | Sulu has no existing way to guide AI content generation | ✓ Good — differentiator |
| Per-webspace + global guidelines | Supports multi-brand Sulu installations with shared defaults | ✓ Good — merge algorithm works |
| OAuth 2.0 for auth | Claude.ai supports OAuth natively, maps to Sulu users | ✓ Good — tested on stage.sulu.io |
| Sulu message bus for writes | CreatePageMessage/ModifyPageMessage with HandleTrait | ✓ Good — consistent across all entities |
| Reactive permission enforcement | Sulu message handlers enforce permissions; McpExceptionListener catches | ⚠️ Revisit — proactive guard would give cleaner errors |
| Block _id for updates | Use Sulu's internal block _id instead of index+property | ✓ Good — simpler API |

---
*Last updated: 2026-04-03 after v1.0 milestone*
