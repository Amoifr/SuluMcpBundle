# Milestones

## v1.0 MVP (Shipped: 2026-04-03)

**Phases completed:** 4 phases, 12 plans, 18 tasks

**Key accomplishments:**

- Symfony bundle skeleton with MCP /_mcp endpoint, sulu_ping tool with webspace/locale validation, and Sulu 3.0 test app
- Four read-only MCP resources exposing Sulu CMS structure: templates with field schemas, block types with deduplication, webspaces with prod URLs, and per-webspace depth-limited sitemap via NavigationRepositoryInterface
- Two Doctrine entities (ContentGuidelines, CompanyContext) with repositories, MCP read resources (sulu://guidelines/{webspace}, sulu://context/company), and MCP write tools — completing the AI content guidelines infrastructure
- Three page read tools (get/list/tree) using Sulu 3.0 PageRepositoryInterface and ContentManagerInterface for page discovery by AI clients
- Page create/update/delete tools dispatching Sulu 3.0 messages via HandleTrait with EnableFlushStamp and ContentManager normalization
- Three block tools (add, remove, reorder) using read-modify-write pattern with parameterized block property and input validation
- Publish/unpublish workflow transition tools and MCP Prompt for AI-driven content guideline generation from existing pages
- 5 article CRUD MCP tools (get, list, create, update, delete) mirroring page tool patterns without webspace/parentId for flat article entities
- 5 article tools completing the article content workflow: block add/remove/reorder + publish/unpublish
- 6 taxonomy tools: tag CRUD (create, list, delete) + category CRUD (create with tree nesting, list tree, delete)
- 7 tools: media (list, get, update metadata) + snippets (get, list) + contacts + navigation

---
