# Phase 1: Bundle Foundation & Transport - Context

**Gathered:** 2026-03-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Symfony bundle skeleton with MCP Streamable HTTP endpoint, OAuth 2.0 authentication flow mapped to Sulu users, and permission guard infrastructure. All MCP tool/resource endpoints accept webspace and locale parameters. This is pure infrastructure — no content tools yet.

</domain>

<decisions>
## Implementation Decisions

### Bundle Identity
- **D-01:** Composer package name: `sulu/mcp-server-bundle`
- **D-02:** PHP namespace: `Sulu\McpServerBundle`
- **D-03:** Bundle class: `SuluMcpServerBundle` (follows Symfony naming conventions)

### Authentication Strategy
- **D-04:** OAuth 2.0 authorization code flow for MCP authentication — compatible with Claude.ai's custom connector dialog (which accepts Remote MCP Server URL + OAuth Client ID + OAuth Client Secret)
- **D-05:** OAuth flow redirects to Sulu admin login page — user authenticates with their existing Sulu credentials, approves the MCP connection, and receives a scoped token
- **D-06:** Token inherits the authenticated Sulu user's permissions — no separate permission model
- **D-07:** The bundle implements the OAuth 2.0 authorization server endpoints (authorize, token) as part of the Symfony bundle — no external OAuth provider needed

### MCP Transport
- **D-08:** Use `symfony/mcp-bundle` with Streamable HTTP transport (single `/_mcp` endpoint, JSON-RPC 2.0)
- **D-09:** MCP tools and resources auto-discovered via PHP 8 attributes (`#[McpTool]`, `#[McpResource]`)

### Claude's Discretion
- Endpoint URL path (default `/_mcp` from symfony/mcp-bundle is fine)
- CORS configuration approach
- Error response structure (follow MCP spec JSON-RPC error format)
- Rate limiting strategy (if any for v1)
- OAuth token expiry and refresh policy
- OAuth client registration mechanism (config-based vs admin UI)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### MCP Protocol
- `.planning/research/STACK.md` — MCP PHP SDK details, symfony/mcp-bundle configuration, Streamable HTTP transport
- `.planning/research/ARCHITECTURE.md` — Four-layer architecture, component boundaries, data flow
- `.planning/research/PITFALLS.md` — Transport pitfalls (FPM worker exhaustion), security requirements (OWASP MCP Top 10)

### Project Context
- `.planning/PROJECT.md` — Project vision, constraints, key decisions
- `.planning/REQUIREMENTS.md` — Phase 1 requirements: TRNS-01–03, AUTH-01–03, LOCL-01–02

### External
- MCP specification (2025-03-26) — Streamable HTTP transport, OAuth 2.0 authorization
- `symfony/mcp-bundle` documentation — Bundle configuration, attribute discovery, transport setup

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- None — greenfield project, no existing code

### Established Patterns
- None — patterns will be established in this phase

### Integration Points
- Bundle registers in Sulu's Symfony kernel
- OAuth endpoints integrate with Sulu's SecurityBundle and user authentication system
- MCP endpoint sits alongside Sulu's existing admin and website routes

</code_context>

<specifics>
## Specific Ideas

- Auth must be compatible with Claude.ai's custom connector dialog (screenshot provided) — Remote MCP Server URL field + optional OAuth Client ID / OAuth Client Secret fields
- Claude.ai connector is the primary integration target for v1

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-bundle-foundation-transport*
*Context gathered: 2026-03-29*
