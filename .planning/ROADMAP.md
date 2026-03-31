# Roadmap: Sulu MCP Server

## Overview

This roadmap delivers a Symfony bundle that exposes Sulu CMS 3.x content operations as MCP tools for AI assistants. The journey starts with the transport and authentication foundation (nothing works without it), builds the context discovery layer AI clients need before they can act, proves the full stack with page content management, then extends proven patterns to all remaining content types. Content guidelines -- the primary differentiator -- ship early in Phase 2 to establish unique market position.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Bundle Foundation & Transport** - Symfony bundle skeleton with MCP endpoint, Sulu user authentication, and permission infrastructure
- [ ] **Phase 2: Context Discovery & Guidelines** - MCP resources exposing templates, blocks, webspace config, sitemap, and the content guidelines system
- [ ] **Phase 3: Page Content Management** - Full page CRUD with block operations and publish/unpublish workflow
- [ ] **Phase 4: Extended Content Tools** - Article CRUD, taxonomy, media, and read-only entity tools

## Phase Details

### Phase 1: Bundle Foundation & Transport
**Goal**: AI clients can connect to the MCP endpoint, authenticate as a Sulu user, and receive structured errors for unauthorized operations
**Depends on**: Nothing (first phase)
**Requirements**: TRNS-01, TRNS-02, TRNS-03, AUTH-01, AUTH-02, AUTH-03, LOCL-01, LOCL-02
**Success Criteria** (what must be TRUE):
  1. An MCP client (e.g., Claude.ai) can connect to the bundle's Streamable HTTP endpoint and complete the MCP handshake
  2. A valid Sulu user credential produces an authenticated session; an invalid credential is rejected
  3. Tool calls made by an authenticated user with insufficient permissions return a structured permission-denied error (not a generic 500)
  4. All MCP tool and resource endpoints accept webspace and locale as parameters
**Plans**: 2 plans

Plans:
- [x] 01-01-PLAN.md -- Bundle skeleton, MCP transport, ping tool, test app, dev tooling (TRNS-01, TRNS-02, TRNS-03, LOCL-01, LOCL-02)
- [x] 01-02-PLAN.md -- OAuth 2.0 authorization server, permission guard, structured errors (AUTH-01, AUTH-02, AUTH-03)

### Phase 2: Context Discovery & Guidelines
**Goal**: AI clients can discover the CMS structure (templates, block types, webspace config, sitemap) and receive content guidelines that shape on-brand content generation
**Depends on**: Phase 1
**Requirements**: RSRC-01, RSRC-02, RSRC-03, RSRC-04, GUID-01, GUID-02, GUID-03, GUID-04
**Success Criteria** (what must be TRUE):
  1. An AI client can read available page templates with field schemas for a given webspace
  2. An AI client can read all available block types with field definitions for a given webspace
  3. An AI client can read webspace configuration (locales, URLs, names) and the content tree/sitemap
  4. Content guidelines (tone, audience, style, brand rules) are retrievable as an MCP resource, with per-webspace overrides merging with global defaults
  5. Company/business context is retrievable as an MCP resource
**Plans**: 2 plans

Plans:
- [x] 02-01-PLAN.md -- MCP resource providers for templates, block types, webspace config, and sitemap (RSRC-01, RSRC-02, RSRC-03, RSRC-04)
- [ ] 02-02-PLAN.md -- Content guidelines system with Doctrine entities, override resolution, MCP resources, and write tools (GUID-01, GUID-02, GUID-03, GUID-04)

### Phase 3: Page Content Management
**Goal**: AI clients can create, read, update, delete, and publish pages with full block management -- the complete page content workflow
**Depends on**: Phase 2
**Requirements**: PAGE-01, PAGE-02, PAGE-03, PAGE-04, PAGE-05, BLCK-01, BLCK-02, BLCK-03, BLCK-04, PUBL-01, PUBL-02
**Success Criteria** (what must be TRUE):
  1. An AI client can get a single page by ID (with all content and blocks) and list/search pages with filters
  2. An AI client can create a page with a chosen template, title, URL, and content, then update or delete it
  3. An AI client can add a block by type, remove a block, and reorder blocks on a page
  4. An AI client can discover all available block types with their field schemas at runtime (before adding blocks)
  5. An AI client can publish and unpublish a page
**Plans**: 4 plans

Plans:
- [x] 03-01-PLAN.md -- Page read tools: get, list, tree (PAGE-01, PAGE-02, BLCK-04)
- [x] 03-02-PLAN.md -- Page write tools: create, update, delete (PAGE-03, PAGE-04, PAGE-05)
- [x] 03-03-PLAN.md -- Block management tools: add, remove, reorder (BLCK-01, BLCK-02, BLCK-03)
- [ ] 03-04-PLAN.md -- Publishing tools and guideline generator prompt (PUBL-01, PUBL-02)

### Phase 4: Extended Content Tools
**Goal**: AI clients can manage articles, tags, categories, media, and read-only entities -- completing the full content management surface
**Depends on**: Phase 3
**Requirements**: ARTC-01, ARTC-02, ARTC-03, ARTC-04, ARTC-05, TAXO-01, TAXO-02, TAXO-03, TAXO-04, TAXO-05, TAXO-06, MDIA-01, MDIA-02, MDIA-03, READ-01, READ-02, READ-03
**Success Criteria** (what must be TRUE):
  1. An AI client can get, list, create, update, and delete articles (following the same patterns as pages)
  2. An AI client can create, list, and delete tags
  3. An AI client can create, list (tree structure), and delete categories
  4. An AI client can list/search media, get media details (metadata, URLs, dimensions), and update media metadata
  5. An AI client can read contacts/accounts, snippets with content, and navigation structures
**Plans**: TBD

Plans:
- [ ] 04-01: Article CRUD and publishing tools
- [ ] 04-02: Taxonomy, media, and read-only entity tools

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Bundle Foundation & Transport | 0/2 | Not started | - |
| 2. Context Discovery & Guidelines | 1/2 | In Progress|  |
| 3. Page Content Management | 0/4 | Not started | - |
| 4. Extended Content Tools | 0/2 | Not started | - |
