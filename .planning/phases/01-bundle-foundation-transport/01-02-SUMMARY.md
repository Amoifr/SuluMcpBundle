---
phase: 01-bundle-foundation-transport
plan: 02
subsystem: security
tags: [oauth2, authentication, authorization, permission-guard, mcp-security, sulu-security]

# Dependency graph
requires:
  - 01-01 (bundle skeleton, DI extension, configuration, services.yaml)
provides:
  - OAuth 2.0 authorization server integration via league/oauth2-server-bundle
  - Well-known discovery endpoints (RFC 9728 PRM + RFC 8414 AS metadata)
  - Sulu user mapping from OAuth tokens via SuluUserResolver
  - PermissionGuard wrapping Sulu SecurityChecker
  - Structured JSON-RPC error responses for permission and auth failures
---

# Plan 01-02 Summary

## What was built

OAuth 2.0 authorization server integration for the Sulu MCP Server bundle, implementing the full MCP authorization flow compatible with Claude.ai's custom connector dialog.

### Task 1: OAuth 2.0 Authorization Server & Well-Known Endpoints

Created the OAuth infrastructure:

- **WellKnownController** — Serves `/.well-known/oauth-protected-resource` (RFC 9728) and `/.well-known/oauth-authorization-server` (RFC 8414) discovery endpoints
- **AuthorizationController** — OAuth authorization code flow with auto-consent for Sulu admin users, renders login form for unauthenticated users
- **SuluUserResolver** — Maps OAuth access tokens back to Sulu User entities, enabling permission-scoped operations
- **SuluOAuthUser** — Wrapper implementing League OAuth UserEntityInterface around Sulu's User
- Updated `composer.json` with `league/oauth2-server-bundle` dependency
- Updated `Configuration.php` with OAuth-specific config options (token_ttl, refresh_token_ttl)
- Updated `routes.yaml` with well-known and authorization routes

### Task 2: Permission Guard & Structured Error Responses

Created the permission and error handling layer:

- **PermissionGuard** — Convenience wrapper around Sulu's SecurityCheckerInterface with page-specific check methods (checkPageView, checkPageCreate, checkPageEdit, checkPageDelete)
- **PermissionDeniedException** — Structured exception carrying security context, permission type, and locale
- **McpExceptionListener** — Converts exceptions on MCP endpoints to JSON-RPC 2.0 error responses with structured data (permission_denied, invalid_params, internal_error)
- **McpAuthenticationListener** — Returns 401 with WWW-Authenticate header containing `resource_metadata` URL per MCP authorization spec
- Unit tests for PermissionGuard, PermissionDeniedException, SuluUserResolver, and McpExceptionListener

## Key files

### Created
- `src/Security/OAuth/WellKnownController.php` — RFC 9728 + RFC 8414 discovery
- `src/Security/OAuth/AuthorizationController.php` — OAuth authorization code flow
- `src/Security/OAuth/SuluUserResolver.php` — OAuth token to Sulu user mapping
- `src/Security/OAuth/SuluOAuthUser.php` — League UserEntityInterface wrapper
- `src/Security/PermissionGuard.php` — Sulu permission checking wrapper
- `src/Security/Exception/PermissionDeniedException.php` — Structured permission error
- `src/EventListener/McpExceptionListener.php` — JSON-RPC error conversion
- `src/EventListener/McpAuthenticationListener.php` — 401 with WWW-Authenticate
- `tests/Unit/Security/PermissionGuardTest.php`
- `tests/Unit/Security/PermissionDeniedExceptionTest.php`
- `tests/Unit/Security/OAuth/SuluUserResolverTest.php`
- `tests/Unit/EventListener/McpExceptionListenerTest.php`

### Modified
- `composer.json` — Added league/oauth2-server-bundle dependency
- `config/services.yaml` — Registered OAuth, security, and listener services
- `config/routes.yaml` — Added well-known and authorize routes
- `src/DependencyInjection/Configuration.php` — Added OAuth config tree
- `src/DependencyInjection/SuluMcpServerExtension.php` — Loads OAuth config

## Deviations

None — implementation follows the plan.

## Self-Check: PASSED

- [x] WellKnownController serves /.well-known/oauth-protected-resource with authorization_endpoint
- [x] WellKnownController serves /.well-known/oauth-authorization-server with token_endpoint
- [x] AuthorizationController handles OAuth authorization code flow
- [x] SuluUserResolver maps OAuth tokens to Sulu User entities
- [x] PermissionGuard wraps SecurityCheckerInterface with convenience methods
- [x] PermissionDeniedException carries securityContext, permissionType, locale
- [x] McpExceptionListener converts PermissionDeniedException to JSON-RPC 403
- [x] McpAuthenticationListener returns 401 with WWW-Authenticate header containing resource_metadata URL
- [x] All services registered in services.yaml with proper arguments
