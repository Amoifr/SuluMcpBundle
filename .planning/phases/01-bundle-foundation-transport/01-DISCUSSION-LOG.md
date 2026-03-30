# Phase 1: Bundle Foundation & Transport - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-29
**Phase:** 01-bundle-foundation-transport
**Areas discussed:** Bundle Identity, Auth Strategy

---

## Bundle Identity

### Package Name

| Option | Description | Selected |
|--------|-------------|----------|
| sulu/mcp-bundle | Official Sulu namespace — implies first-party | |
| sulu/mcp-server-bundle | More descriptive — clarifies it's the server | ✓ |

**User's choice:** sulu/mcp-server-bundle
**Notes:** None

### PHP Namespace

| Option | Description | Selected |
|--------|-------------|----------|
| Sulu\Bundle\McpServerBundle | Standard Sulu bundle pattern | |
| Sulu\McpServerBundle | Shorter, flatter namespace | ✓ |

**User's choice:** Sulu\McpServerBundle
**Notes:** None

---

## Auth Strategy

### Auth Flow

| Option | Description | Selected |
|--------|-------------|----------|
| OAuth 2.0 + Sulu login | Authorization code flow, redirects to Sulu login | ✓ |
| OAuth + API tokens | OAuth flow + pre-authorized API tokens for headless use | |

**User's choice:** OAuth 2.0 + Sulu login
**Notes:** User provided screenshot of Claude.ai's custom connector dialog showing Remote MCP Server URL + optional OAuth Client ID / OAuth Client Secret fields. Auth must be compatible with this.

---

## Claude's Discretion

- Endpoint URL path
- CORS configuration
- Error response structure
- Rate limiting
- OAuth token expiry/refresh policy
- OAuth client registration mechanism
