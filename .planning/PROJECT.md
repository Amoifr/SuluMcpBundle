# Sulu MCP Server

## What This Is

A Symfony bundle for Sulu CMS 3.x that exposes content management operations as MCP (Model Context Protocol) tools via HTTP/SSE. It enables AI assistants like Claude.ai and ChatGPT to manage Sulu content directly — creating pages, adding blocks, publishing articles, managing media, and more — while respecting Sulu's user permissions and multi-webspace architecture.

## Core Value

AI assistants can create, edit, and publish content in Sulu CMS with full awareness of the project's content guidelines, templates, and brand context — writing on-brand content, not just executing CRUD.

## Requirements

### Validated

- [x] Pages: Get, create, edit, delete pages via Sulu services — Validated in Phase 3: page-content-management
- [x] Blocks: Add, remove, reorder blocks on pages/articles with dynamic discovery of all available block types — Validated in Phase 3: page-content-management
- [x] Publishing: Publish and unpublish pages and articles — Validated in Phase 3: page-content-management
- [x] Articles: Get, create, edit, delete articles via Sulu services — Validated in Phase 4: extended-content-tools
- [x] Tags: Create, get, delete tags — Validated in Phase 4: extended-content-tools
- [x] Categories: Create, get, delete categories — Validated in Phase 4: extended-content-tools
- [x] Media/Assets: List, get details, update metadata — Validated in Phase 4: extended-content-tools
- [x] Snippets: Read reusable content snippets — Validated in Phase 4: extended-content-tools
- [x] Navigation: Read navigation structures — Validated in Phase 4: extended-content-tools

### Active

- [ ] HTTP/SSE transport for MCP communication
- [ ] Sulu user authentication — inherit user permissions for all operations
- [ ] Webspace and locale passed as parameters per tool call
- [ ] Content guidelines system — global defaults with per-webspace overrides (tone, audience, style, brand rules)
- [ ] MCP resources exposing live context: available templates, block types, sitemap, webspace config, company/business context, content guidelines
- [ ] Auto-generated system prompt/instructions file for Claude.ai/ChatGPT project setup
- [ ] Exportable prompt for manual AI client setup
- [ ] MCP gateway compatibility for ChatGPT integration

### Out of Scope

- REST API layer — bundle uses Sulu services directly, no API indirection
- Stdio transport — HTTP/SSE only (remote/cloud deployments are the target)
- Concurrency locking — not in v1, may revisit if concurrent AI sessions become an issue
- Sulu admin UI for guidelines management — v1 stores guidelines in config/database, admin UI deferred
- Custom block type creation — the bundle discovers existing block types, it doesn't create new ones

## Context

- **Sulu 3.x**: Next major version of Sulu CMS. The bundle targets this version exclusively.
- **PHP 8.2+**: Minimum PHP version, aligned with Sulu 3.x requirements.
- **Symfony bundle**: Lives inside the Sulu project, has full access to the DI container and all Sulu services.
- **MCP protocol**: Model Context Protocol — the open standard for connecting AI assistants to external tools and data. Claude.ai supports MCP natively; ChatGPT uses it via MCP gateways/bridges.
- **Content guidelines are new**: Sulu has no existing concept of AI content guidelines. This bundle introduces the storage and exposure of tone, audience, style, and brand context as a first-class feature.
- **Dynamic block discovery**: Sulu projects configure their own block types. The bundle must introspect available blocks at runtime rather than hardcoding a set.
- **Multi-webspace**: Sulu supports multiple websites from one installation. All tools accept webspace/locale parameters. Content guidelines support global defaults with per-webspace overrides.

## Constraints

- **Tech Stack**: PHP 8.2+, Symfony bundle, Sulu 3.x — no external runtime dependencies
- **Auth**: Must use Sulu's native user authentication — no separate auth system
- **Transport**: HTTP/SSE only — designed for network-accessible deployments
- **Permissions**: All operations must respect the authenticated Sulu user's permissions — no privilege escalation

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Direct service usage over REST API | Avoids API layer overhead, leverages full Sulu service capabilities | -- Pending |
| Symfony bundle over standalone process | Access to DI container, services, and Sulu kernel without bootstrapping | -- Pending |
| HTTP/SSE over stdio | Needed for remote/cloud deployments and web-based AI clients | -- Pending |
| Dynamic block discovery | Projects configure custom blocks; hardcoding would limit adoption | -- Pending |
| Content guidelines as new concept | Sulu has no existing way to guide AI content generation; this fills the gap | -- Pending |
| Per-webspace + global guidelines | Supports multi-brand Sulu installations with shared defaults | -- Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-03-31 — Phase 04 complete (extended-content-tools: articles, taxonomy, media, snippets, contacts, navigation)*
