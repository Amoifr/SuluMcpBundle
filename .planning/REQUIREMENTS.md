# Requirements: Sulu MCP Server

**Defined:** 2026-03-29
**Core Value:** AI assistants can create, edit, and publish content in Sulu CMS with full awareness of content guidelines, templates, and brand context.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Transport & Infrastructure

- [ ] **TRNS-01**: MCP server communicates via Streamable HTTP transport (single endpoint, JSON-RPC 2.0)
- [ ] **TRNS-02**: Bundle registers as a Symfony bundle with full DI container access
- [ ] **TRNS-03**: MCP tools, resources, and prompts are auto-discovered via PHP 8 attributes

### Authentication & Authorization

- [ ] **AUTH-01**: User authenticates via Sulu user credentials mapped to bearer token
- [ ] **AUTH-02**: All operations respect the authenticated Sulu user's permissions
- [ ] **AUTH-03**: Unauthorized operations return structured permission-denied errors

### Pages

- [ ] **PAGE-01**: Get a single page by ID with all content and blocks
- [ ] **PAGE-02**: List/search pages with filtering by webspace, locale, template
- [ ] **PAGE-03**: Create a page with template, title, URL, and content
- [ ] **PAGE-04**: Update page properties and content
- [ ] **PAGE-05**: Delete a page

### Articles

- [ ] **ARTC-01**: Get a single article by ID with all content
- [ ] **ARTC-02**: List/search articles with filtering
- [ ] **ARTC-03**: Create an article with type, title, and content
- [ ] **ARTC-04**: Update article properties and content
- [ ] **ARTC-05**: Delete an article

### Blocks

- [ ] **BLCK-01**: Add a block to a page or article by block type
- [ ] **BLCK-02**: Remove a block from a page or article
- [ ] **BLCK-03**: Reorder blocks on a page or article
- [ ] **BLCK-04**: Dynamic discovery of all available block types with field schemas at runtime

### Publishing

- [ ] **PUBL-01**: Publish a page or article
- [ ] **PUBL-02**: Unpublish a page or article

### Taxonomy

- [ ] **TAXO-01**: Create a tag
- [ ] **TAXO-02**: List tags
- [ ] **TAXO-03**: Delete a tag
- [ ] **TAXO-04**: Create a category
- [ ] **TAXO-05**: List categories (tree structure)
- [ ] **TAXO-06**: Delete a category

### Media

- [ ] **MDIA-01**: List/search media with filtering by collection, type, tags
- [ ] **MDIA-02**: Get media details (metadata, URLs, dimensions)
- [ ] **MDIA-03**: Update media metadata (alt text, title, copyright)

### Read-Only Entities

- [ ] **READ-01**: Get/list contacts and accounts
- [ ] **READ-02**: Get/list snippets with content
- [ ] **READ-03**: Get navigation structures

### MCP Resources (Context Discovery)

- [ ] **RSRC-01**: Expose available page templates with field schemas per webspace
- [ ] **RSRC-02**: Expose available block types with field definitions per webspace
- [ ] **RSRC-03**: Expose webspace configuration (locales, URLs, names)
- [ ] **RSRC-04**: Expose sitemap/content tree per webspace and locale

### Content Guidelines

- [ ] **GUID-01**: Store content guidelines (tone, audience, style, brand rules) with global defaults
- [ ] **GUID-02**: Support per-webspace guideline overrides that merge with global defaults
- [ ] **GUID-03**: Expose guidelines as MCP resource at `sulu://guidelines/{webspace}`
- [ ] **GUID-04**: Expose company/business context as MCP resource at `sulu://context/company`

### Localization

- [ ] **LOCL-01**: All content tools accept webspace and locale as parameters
- [ ] **LOCL-02**: Resource endpoints return locale-appropriate data

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced AI Features

- **ADVN-01**: Auto-generated system prompt from CMS context + guidelines + templates + blocks
- **ADVN-02**: Exportable prompt document for manual AI client setup
- **ADVN-03**: Content validation feedback with field-level structured errors
- **ADVN-04**: Template-aware enriched page creation (pre-load template schema into creation context)

### Extended Content Operations

- **EXTD-01**: Snippet CRUD (create, update, delete — not just read)
- **EXTD-02**: Media upload from URL
- **EXTD-03**: Navigation management (write operations)
- **EXTD-04**: Locale-aware translation suggestions (what locales are missing)

### Integration

- **INTG-01**: MCP gateway compatibility documentation and testing for ChatGPT
- **INTG-02**: Sulu admin UI for managing content guidelines

## Out of Scope

| Feature | Reason |
|---------|--------|
| REST API layer | Bundle uses Sulu services directly — no API indirection |
| Stdio transport | HTTP/SSE only for remote/cloud deployments |
| Direct AI auto-publishing | Safety concern — AI creates drafts, humans review and publish deliberately |
| Full REST API proxy | Violates MCP best practice of outcome-oriented tools; creates tool sprawl |
| Real-time collaborative editing | Requires OT/CRDT, massive complexity; no CMS MCP server does this |
| Custom block type creation | Block types define site architecture — remains a developer task |
| AI-generated media | Separate concern from CMS content management; requires external services |
| Bulk operations across all content | Dangerous with AI agents; one bad prompt updates hundreds of pages |
| Content workflow states beyond draft/published | Sulu's core is draft/published; custom workflows are a future concern |
| Undo/revision history browsing | Sulu tracks versions natively; humans revert in admin UI |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| TRNS-01 | Phase ? | Pending |
| TRNS-02 | Phase ? | Pending |
| TRNS-03 | Phase ? | Pending |
| AUTH-01 | Phase ? | Pending |
| AUTH-02 | Phase ? | Pending |
| AUTH-03 | Phase ? | Pending |
| PAGE-01 | Phase ? | Pending |
| PAGE-02 | Phase ? | Pending |
| PAGE-03 | Phase ? | Pending |
| PAGE-04 | Phase ? | Pending |
| PAGE-05 | Phase ? | Pending |
| ARTC-01 | Phase ? | Pending |
| ARTC-02 | Phase ? | Pending |
| ARTC-03 | Phase ? | Pending |
| ARTC-04 | Phase ? | Pending |
| ARTC-05 | Phase ? | Pending |
| BLCK-01 | Phase ? | Pending |
| BLCK-02 | Phase ? | Pending |
| BLCK-03 | Phase ? | Pending |
| BLCK-04 | Phase ? | Pending |
| PUBL-01 | Phase ? | Pending |
| PUBL-02 | Phase ? | Pending |
| TAXO-01 | Phase ? | Pending |
| TAXO-02 | Phase ? | Pending |
| TAXO-03 | Phase ? | Pending |
| TAXO-04 | Phase ? | Pending |
| TAXO-05 | Phase ? | Pending |
| TAXO-06 | Phase ? | Pending |
| MDIA-01 | Phase ? | Pending |
| MDIA-02 | Phase ? | Pending |
| MDIA-03 | Phase ? | Pending |
| READ-01 | Phase ? | Pending |
| READ-02 | Phase ? | Pending |
| READ-03 | Phase ? | Pending |
| RSRC-01 | Phase ? | Pending |
| RSRC-02 | Phase ? | Pending |
| RSRC-03 | Phase ? | Pending |
| RSRC-04 | Phase ? | Pending |
| GUID-01 | Phase ? | Pending |
| GUID-02 | Phase ? | Pending |
| GUID-03 | Phase ? | Pending |
| GUID-04 | Phase ? | Pending |
| LOCL-01 | Phase ? | Pending |
| LOCL-02 | Phase ? | Pending |

**Coverage:**
- v1 requirements: 43 total
- Mapped to phases: 0
- Unmapped: 43

---
*Requirements defined: 2026-03-29*
*Last updated: 2026-03-29 after initial definition*
