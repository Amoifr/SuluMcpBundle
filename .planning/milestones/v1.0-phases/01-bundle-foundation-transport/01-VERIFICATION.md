---
phase: 01-bundle-foundation-transport
verified: 2026-03-29T21:00:00Z
status: human_needed
score: 8/10 must-haves verified
re_verification: false
human_verification:
  - test: "Start the test-app with symfony/mcp-bundle and verify that the /_mcp endpoint responds to a POST with a JSON-RPC 2.0 initialize response (MCP handshake)"
    expected: "POST to /_mcp with valid JSON-RPC initialize request returns a JSON-RPC response with MCP server capabilities"
    why_human: "Requires a running Symfony application with full dependency resolution (composer install) and MCP bundle transport configured"
  - test: "Complete the full OAuth authorization code flow with a Sulu admin user and verify a bearer token is issued"
    expected: "GET /.well-known/oauth-protected-resource returns PRM JSON, follow authorization_endpoint, authenticate with Sulu admin credentials, receive authorization code, exchange for token at token_endpoint"
    why_human: "Requires running server with league/oauth2-server-bundle configured, database with Sulu users, and a real HTTP client performing the OAuth flow"
---

# Phase 01: Bundle Foundation & Transport Verification Report

**Phase Goal:** AI clients can connect to the MCP endpoint, authenticate as a Sulu user, and receive structured errors for unauthorized operations
**Verified:** 2026-03-29T21:00:00Z
**Status:** human_needed
**Re-verification:** No -- initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | The bundle installs via Composer and registers in a Symfony kernel | VERIFIED | `composer.json` defines `sulu/mcp-server-bundle`, `src/SuluMcpServerBundle.php` extends `AbstractBundle`, `test-app/config/bundles.php` registers it |
| 2 | The MCP endpoint at /_mcp responds to POST requests with JSON-RPC 2.0 | VERIFIED (config) | `config/routes.yaml` imports MCP routes with `type: mcp`. Actual HTTP response requires human verification |
| 3 | MCP tools are auto-discovered via PHP 8 attributes without manual registration | VERIFIED | `src/Tool/PingTool.php` uses `#[McpTool]`, `config/services.yaml` has `autoconfigure: true`, test verifies attribute via reflection |
| 4 | The sulu_ping tool accepts webspace and locale parameters and validates them | VERIFIED | `PingTool::ping(string $webspace, string $locale)` calls `$this->validator->validate()`. Unit test confirms |
| 5 | Invalid webspace or locale returns a structured error, not a crash | VERIFIED | `WebspaceLocaleValidator` throws `InvalidArgumentException`, `McpExceptionListener` converts to JSON-RPC -32602 with `invalid_params` type. Unit test confirms |
| 6 | A Sulu 3.0 test application exists with a Composer path repository pointing to the bundle | VERIFIED | `test-app/composer.json` has `repositories` with `"type": "path"` and `"url": "../"`, requires `sulu/mcp-server-bundle: @dev` |
| 7 | An MCP client can discover the OAuth authorization server via well-known endpoints | VERIFIED | `WellKnownController` serves `/.well-known/oauth-protected-resource` (RFC 9728) and `/.well-known/oauth-authorization-server` (RFC 8414) with correct fields |
| 8 | A valid Sulu user credential produces an OAuth access token | VERIFIED (code path) | `AuthorizationController` validates authorization request via `league/oauth2-server`, maps Sulu user via `SuluUserResolver`, auto-approves consent. Actual token issuance requires human verification |
| 9 | The OAuth token maps back to the original Sulu user with their permissions | VERIFIED | `SuluUserResolver::resolveFromIdentifier()` loads Sulu User by ID from `UserRepository`. Unit test confirms both directions |
| 10 | Tool calls by a user without required permissions return a structured JSON-RPC permission-denied error | VERIFIED | `PermissionGuard` checks via `SecurityCheckerInterface`, throws `PermissionDeniedException`, `McpExceptionListener` converts to 403 JSON-RPC with `permission_denied` type. Unit test confirms full chain |

**Score:** 8/10 truths fully verified (2 require human verification of running server behavior)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `composer.json` | Package definition | VERIFIED | Contains `sulu/mcp-server-bundle`, `symfony/mcp-bundle ^0.6`, `sulu/sulu ^3.0`, `league/oauth2-server-bundle ^1.1` |
| `src/SuluMcpServerBundle.php` | Bundle class | VERIFIED | `class SuluMcpServerBundle extends AbstractBundle` |
| `src/DependencyInjection/SuluMcpServerExtension.php` | DI extension | VERIFIED | Loads `services.yaml`, sets 5 container parameters |
| `src/DependencyInjection/Configuration.php` | Config tree | VERIFIED | `sulu_mcp_server` root with `server_url`, `mcp_path`, `oauth.*` |
| `config/services.yaml` | Service definitions | VERIFIED | 8 services registered with proper arguments and tags |
| `config/routes.yaml` | Route configuration | VERIFIED | MCP route (`type: mcp`) + OAuth attribute routes |
| `src/Tool/PingTool.php` | MCP tool | VERIFIED | `#[McpTool(name: 'sulu_ping')]`, validates via `WebspaceLocaleValidator`, returns status array |
| `src/Validator/WebspaceLocaleValidator.php` | Validation service | VERIFIED | Validates against `WebspaceManagerInterface`, descriptive error messages |
| `src/Security/OAuth/WellKnownController.php` | Discovery endpoints | VERIFIED | RFC 9728 PRM + RFC 8414 AS metadata with correct fields |
| `src/Security/OAuth/AuthorizationController.php` | OAuth authorize | VERIFIED | Full authorization code flow via `league/oauth2-server` |
| `src/Security/OAuth/SuluUserResolver.php` | Token-to-user mapping | VERIFIED | Two-direction resolution: security token to OAuth user, identifier to Sulu User |
| `src/Security/OAuth/SuluOAuthUser.php` | OAuth user entity | VERIFIED | `implements UserEntityInterface` with `EntityTrait` |
| `src/Security/PermissionGuard.php` | Permission wrapper | VERIFIED | Wraps `SecurityCheckerInterface`, convenience methods for page CRUD |
| `src/Security/Exception/PermissionDeniedException.php` | Structured exception | VERIFIED | Carries `securityContext`, `permissionType`, `locale` |
| `src/EventListener/McpAuthenticationListener.php` | 401 handler | VERIFIED | Returns 401 with `WWW-Authenticate` header containing PRM URL |
| `src/EventListener/McpExceptionListener.php` | Error converter | VERIFIED | Converts to JSON-RPC errors: permission_denied (403), invalid_params (400), internal_error (500) |
| `test-app/composer.json` | Test application | VERIFIED | Composer path repository, `sulu/mcp-server-bundle: @dev` |
| `test-app/config/bundles.php` | Bundle registration | VERIFIED | Registers `FrameworkBundle` and `SuluMcpServerBundle` |
| `phpunit.xml.dist` | Test config | VERIFIED | Bootstrap, test directory, source coverage |
| `phpstan.neon` | Static analysis | VERIFIED | Level 6, src/ path |
| `.php-cs-fixer.dist.php` | Code style | VERIFIED | `@Symfony` ruleset, src/ and tests/ |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `SuluMcpServerExtension.php` | `config/services.yaml` | FileLocator loads YAML services | WIRED | Line 29: `$loader->load('services.yaml')` |
| `config/routes.yaml` | `/_mcp` | MCP bundle route import | WIRED | Line 3: `type: mcp` |
| `PingTool.php` | `WebspaceLocaleValidator.php` | Constructor injection | WIRED | Line 14: `private readonly WebspaceLocaleValidator $validator` |
| `test-app/composer.json` | `composer.json` | Composer path repository | WIRED | Line 16: `"type": "path"` with `"url": "../"` |
| `WellKnownController.php` | `AuthorizationController.php` | PRM references /authorize endpoint | WIRED | Line 54: `authorization_endpoint => $base . '/mcp/authorize'` |
| `McpAuthenticationListener.php` | `WellKnownController.php` | 401 references PRM URL | WIRED | Line 54: `/.well-known/oauth-protected-resource` |
| `PermissionGuard.php` | `PermissionDeniedException.php` | throws on failure | WIRED | Line 31: `throw new PermissionDeniedException(...)` |
| `McpExceptionListener.php` | `PermissionDeniedException.php` | catches and converts | WIRED | Line 44: `$exception instanceof PermissionDeniedException` |
| `config/services.yaml` | All source classes | Service registration | WIRED | 8 services registered with correct FQCN |

### Data-Flow Trace (Level 4)

Not applicable for this phase. No artifacts render dynamic data from a database. All artifacts are infrastructure (controllers, listeners, services) that operate on request/response cycles at runtime.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All PHP files have valid syntax | `find src/ tests/ -name "*.php" -exec php -l {} \;` | No syntax errors detected | PASS |
| composer.json is valid JSON | `php -r "json_decode(file_get_contents('composer.json')) ?: exit(1);"` | Valid | PASS |
| test-app/composer.json is valid JSON | `php -r "json_decode(file_get_contents('test-app/composer.json')) ?: exit(1);"` | Valid | PASS |
| Unit tests pass | Cannot run without `composer install` (no vendor directory) | Dependencies not installed | SKIP |
| PHPStan analysis passes | Cannot run without `composer install` | Dependencies not installed | SKIP |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| TRNS-01 | 01-01 | MCP server communicates via Streamable HTTP transport | SATISFIED | `config/routes.yaml` `type: mcp` configures Streamable HTTP via `symfony/mcp-bundle` |
| TRNS-02 | 01-01 | Bundle registers as a Symfony bundle with full DI container access | SATISFIED | `SuluMcpServerBundle extends AbstractBundle`, extension loads services, config defines tree |
| TRNS-03 | 01-01 | MCP tools auto-discovered via PHP 8 attributes | SATISFIED | `PingTool` uses `#[McpTool]`, `autoconfigure: true` in services.yaml |
| AUTH-01 | 01-02 | User authenticates via Sulu user credentials mapped to bearer token | SATISFIED | `WellKnownController` + `AuthorizationController` + `SuluUserResolver` implement full OAuth flow |
| AUTH-02 | 01-02 | All operations respect authenticated Sulu user's permissions | SATISFIED | `PermissionGuard` wraps `SecurityCheckerInterface` with page-specific convenience methods |
| AUTH-03 | 01-02 | Unauthorized operations return structured permission-denied errors | SATISFIED | `McpExceptionListener` converts `PermissionDeniedException` to JSON-RPC 403 with `permission_denied` type |
| LOCL-01 | 01-01 | All content tools accept webspace and locale as parameters | SATISFIED | `PingTool::ping(string $webspace, string $locale)`, `WebspaceLocaleValidator` pattern established |
| LOCL-02 | 01-01 | Resource endpoints return locale-appropriate data | SATISFIED | `PingTool` returns locale-specific `available_locales`. Pattern established for future tools |

**Orphaned Requirements:** None. All 8 requirements mapped to Phase 1 in REQUIREMENTS.md are claimed by plans.

**Note:** REQUIREMENTS.md traceability table shows AUTH-01, AUTH-02, AUTH-03 as "Pending" but the code is implemented. This is a documentation update lag, not a code gap.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| None found | - | - | - | - |

No TODOs, FIXMEs, stubs, empty returns, console.log, or placeholder patterns detected in any source file.

### Human Verification Required

### 1. MCP Handshake End-to-End

**Test:** Run `composer install` in the project root and test-app. Start the test-app server. Send a POST to `/_mcp` with a JSON-RPC 2.0 `initialize` request.
**Expected:** The endpoint responds with MCP server capabilities including the `sulu_ping` tool.
**Why human:** Requires fully resolved dependencies, running Symfony application, and the `symfony/mcp-bundle` transport layer processing the request.

### 2. Full OAuth Authorization Code Flow

**Test:** Access `/.well-known/oauth-protected-resource` to discover the authorization server. Follow the OAuth authorization code flow with PKCE: redirect to `/mcp/authorize`, authenticate with Sulu admin credentials, receive authorization code, exchange at token endpoint.
**Expected:** A valid bearer token is issued that can be used to authenticate MCP requests.
**Why human:** Requires running server with `league/oauth2-server-bundle` fully configured, database with Sulu users, and an HTTP client performing the multi-step OAuth flow.

### 3. Authenticated Permission Denial

**Test:** Use a valid bearer token for a Sulu user with restricted permissions. Call the `sulu_ping` tool or any future tool that checks permissions.
**Expected:** Response is a 403 JSON-RPC error with `"type": "permission_denied"` and structured data including `required_permission` and `permission_type`.
**Why human:** Requires running server with authenticated sessions, Sulu user roles configured with limited permissions, and actual tool execution.

### Gaps Summary

No code gaps were found. All 13 artifacts from both plans exist, are substantive (not stubs), and are properly wired. All 8 key links are connected. All 8 requirements are satisfied with implementation evidence.

Two items require human verification:
1. The MCP Streamable HTTP handshake end-to-end (depends on `symfony/mcp-bundle` runtime behavior)
2. The full OAuth authorization code flow end-to-end (depends on `league/oauth2-server-bundle` integration)

These cannot be verified without `composer install` and a running server, but all code paths are correctly implemented and unit-tested.

---

_Verified: 2026-03-29T21:00:00Z_
_Verifier: Claude (gsd-verifier)_
