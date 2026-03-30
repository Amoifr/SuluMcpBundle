# Phase 1: Bundle Foundation & Transport - Research

**Researched:** 2026-03-29
**Domain:** Symfony bundle skeleton, MCP Streamable HTTP transport, OAuth 2.0 authorization server, Sulu user mapping, permission guard infrastructure
**Confidence:** HIGH

## Summary

Phase 1 establishes the foundational infrastructure: a Symfony bundle (`sulu/mcp-server-bundle`) that exposes an MCP Streamable HTTP endpoint, implements an OAuth 2.0 authorization server compatible with Claude.ai's custom connector dialog, maps OAuth tokens to Sulu users with their existing permissions, and returns structured JSON-RPC errors for authorization failures. All MCP endpoints accept webspace and locale parameters.

The critical technical challenge is the OAuth 2.0 implementation. The MCP specification (draft, evolved from 2025-03-26) requires the MCP server to act as an OAuth 2.0 resource server and to expose Protected Resource Metadata (RFC 9728). It also requires an authorization server that supports the authorization code grant with PKCE. Per user decision D-07, the bundle itself implements the authorization server endpoints -- no external OAuth provider. The recommended approach is `league/oauth2-server-bundle` (v1.1.1), which wraps `league/oauth2-server` v9 and provides PKCE-enabled authorization code flow, token management, and Symfony Security integration out of the box.

The `symfony/mcp-bundle` (v0.6.0) handles MCP protocol concerns: Streamable HTTP transport, JSON-RPC 2.0, session management, and auto-discovery of tools/resources/prompts via PHP 8 attributes. It has **no built-in authentication/authorization**. Authentication must be implemented at the Symfony firewall level, sitting in front of the MCP endpoint.

**Primary recommendation:** Use `league/oauth2-server-bundle` for the OAuth authorization server, `symfony/mcp-bundle` for MCP transport, and Symfony Security firewalls to wire them together. Implement a dummy `#[McpTool]` (e.g., `sulu_ping`) to validate the full stack end-to-end in this phase.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Composer package name: `sulu/mcp-server-bundle`
- **D-02:** PHP namespace: `Sulu\McpServerBundle`
- **D-03:** Bundle class: `SuluMcpServerBundle` (follows Symfony naming conventions)
- **D-04:** OAuth 2.0 authorization code flow for MCP authentication -- compatible with Claude.ai's custom connector dialog (which accepts Remote MCP Server URL + OAuth Client ID + OAuth Client Secret)
- **D-05:** OAuth flow redirects to Sulu admin login page -- user authenticates with their existing Sulu credentials, approves the MCP connection, and receives a scoped token
- **D-06:** Token inherits the authenticated Sulu user's permissions -- no separate permission model
- **D-07:** The bundle implements the OAuth 2.0 authorization server endpoints (authorize, token) as part of the Symfony bundle -- no external OAuth provider needed
- **D-08:** Use `symfony/mcp-bundle` with Streamable HTTP transport (single `/_mcp` endpoint, JSON-RPC 2.0)
- **D-09:** MCP tools and resources auto-discovered via PHP 8 attributes (`#[McpTool]`, `#[McpResource]`)

### Claude's Discretion
- Endpoint URL path (default `/_mcp` from symfony/mcp-bundle is fine)
- CORS configuration approach
- Error response structure (follow MCP spec JSON-RPC error format)
- Rate limiting strategy (if any for v1)
- OAuth token expiry and refresh policy
- OAuth client registration mechanism (config-based vs admin UI)

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| TRNS-01 | MCP server communicates via Streamable HTTP transport (single endpoint, JSON-RPC 2.0) | symfony/mcp-bundle v0.6 provides StreamableHttpTransport, configured via `mcp.http.path` YAML key. Routing via `resource: . / type: mcp`. |
| TRNS-02 | Bundle registers as a Symfony bundle with full DI container access | Standard Symfony bundle skeleton: `SuluMcpServerBundle` class, `SuluMcpServerExtension` DI extension, `Configuration` tree. |
| TRNS-03 | MCP tools, resources, and prompts are auto-discovered via PHP 8 attributes | symfony/mcp-bundle auto-scans `src/` for `#[McpTool]`, `#[McpResource]`, `#[McpResourceTemplate]`, `#[McpPrompt]` attributes. Zero manual registration. |
| AUTH-01 | User authenticates via Sulu user credentials mapped to bearer token | OAuth 2.0 authorization code flow: user logs in via Sulu admin, league/oauth2-server issues JWT/opaque bearer token mapped to Sulu user. |
| AUTH-02 | All operations respect the authenticated Sulu user's permissions | Bearer token resolves to Sulu User in SecurityContext. PermissionGuard wraps every tool call, delegating to Sulu's SecurityChecker. |
| AUTH-03 | Unauthorized operations return structured permission-denied errors | JSON-RPC error response with code -32603 (or custom), structured `data` field containing error code, message, and required permission. |
| LOCL-01 | All content tools accept webspace and locale as parameters | Architectural pattern: every `#[McpTool]` method signature includes `string $webspace, string $locale` as required parameters. Validated against WebspaceManager. |
| LOCL-02 | Resource endpoints return locale-appropriate data | MCP resources that serve content-related data accept locale parameter and return data filtered/resolved for that locale. |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- Use `symfony composer <script>` commands for running phpstan, test, lint, fix and other composer scripts
- Never include Claude name/co-author in commit messages
- Never include AI attribution (Claude naming)
- Follow GSD workflow for all file changes

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| symfony/mcp-bundle | ^0.6 (v0.6.0, 2026-03-04) | MCP Streamable HTTP transport, attribute discovery, session management | Official Symfony MCP integration. Wraps mcp/sdk. Used by Symfony ecosystem. |
| mcp/sdk | ^0.4 (v0.4.0, 2026-02-23) | MCP protocol SDK (JSON-RPC, transport, capabilities) | Official PHP SDK maintained by Symfony team + PHP Foundation + Anthropic. |
| league/oauth2-server-bundle | ^1.1 (v1.1.1, 2026-02-06) | OAuth 2.0 authorization server (authorize, token, client management) | Standard Symfony OAuth server. Wraps league/oauth2-server v9. Supports PKCE, auth code grant, Symfony Security integration. |
| league/oauth2-server | ^9.2 (dependency of above) | OAuth 2.0 server core | The PHP OAuth server library. PKCE built-in, auth code grant, refresh tokens, client credentials. |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| nyholm/psr7 | ^1.4 | PSR-7 HTTP messages | Required by league/oauth2-server-bundle. Already pulled in. |
| symfony/security-bundle | ^7.3 | Symfony firewall, authentication | Already in Sulu. Used to protect MCP endpoint with OAuth bearer token. |
| symfony/framework-bundle | ^7.3 | Symfony kernel, DI, routing | Already in Sulu. Bundle foundation. |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| league/oauth2-server-bundle | Hand-rolled OAuth endpoints | Never. OAuth is deceptively complex (PKCE, token rotation, RFC compliance). League handles it correctly. |
| league/oauth2-server-bundle | External OAuth provider (Keycloak, Auth0) | Violates D-07 (bundle must implement its own OAuth). Adds infrastructure dependency. |
| league/oauth2-server-bundle | trikoder/oauth2-bundle | Deprecated in favor of league/oauth2-server-bundle. Same maintainers. |

**Installation:**
```bash
composer require symfony/mcp-bundle league/oauth2-server-bundle
```

## Architecture Patterns

### Recommended Project Structure

```
src/
  SuluMcpServerBundle.php                       # Bundle class
  DependencyInjection/
    SuluMcpServerExtension.php                  # Loads services, configures MCP + OAuth
    Configuration.php                           # Bundle config tree (sulu_mcp_server)
  Security/
    OAuth/
      SuluUserRepository.php                    # league/oauth2-server UserEntityInterface -> Sulu User
      SuluClientRepository.php                  # OAuth client entity/repository (config or DB based)
      SuluScopeRepository.php                   # MCP scopes (mcp:tools, mcp:resources)
      SuluAccessTokenRepository.php             # Token persistence (DB via Doctrine)
      SuluAuthCodeRepository.php                # Auth code persistence
      SuluRefreshTokenRepository.php            # Refresh token persistence
    PermissionGuard.php                         # Wraps Sulu SecurityChecker for MCP tool calls
  Controller/
    AuthorizationController.php                 # GET /authorize (renders Sulu login + consent)
    WellKnownController.php                     # /.well-known/oauth-protected-resource (RFC 9728 PRM)
  Entity/
    OAuthClient.php                             # Doctrine entity for OAuth clients (if DB-based)
    OAuthAccessToken.php                        # Doctrine entity (may use league defaults)
    OAuthAuthCode.php                           # Doctrine entity
    OAuthRefreshToken.php                       # Doctrine entity
  Tool/
    PingTool.php                                # #[McpTool] smoke test tool (Phase 1 only)
  EventListener/
    McpExceptionListener.php                    # Catches exceptions, returns structured JSON-RPC errors
  config/
    services.yaml                               # Service definitions
    routes.yaml                                 # OAuth + MCP route registration
    packages/
      sulu_mcp_server.yaml                      # Default bundle configuration
```

### Pattern 1: OAuth 2.0 Authorization Flow (MCP-Compliant)

**What:** The bundle acts as both the OAuth 2.0 authorization server AND the MCP resource server. Claude.ai (or any MCP client) authenticates users through an OAuth authorization code flow with PKCE.

**When to use:** Every MCP client connection.

**Flow:**
```
1. Claude.ai sends POST /_mcp (no token)
2. Bundle returns 401 Unauthorized with:
   WWW-Authenticate: Bearer resource_metadata="https://sulu.example.com/.well-known/oauth-protected-resource"

3. Claude.ai fetches /.well-known/oauth-protected-resource
   Returns: { "resource": "https://sulu.example.com/_mcp",
              "authorization_servers": ["https://sulu.example.com"],
              "scopes_supported": ["mcp:tools", "mcp:resources"] }

4. Claude.ai fetches /.well-known/oauth-authorization-server
   Returns: { "issuer": "https://sulu.example.com",
              "authorization_endpoint": "https://sulu.example.com/authorize",
              "token_endpoint": "https://sulu.example.com/token",
              "code_challenge_methods_supported": ["S256"],
              "grant_types_supported": ["authorization_code", "refresh_token"] }

5. Claude.ai opens browser to /authorize?client_id=...&code_challenge=...&redirect_uri=https://claude.ai/api/mcp/auth_callback
6. Sulu login page renders. User logs in with Sulu credentials.
7. User approves MCP access. Bundle generates auth code.
8. Redirect to Claude.ai callback with auth code.
9. Claude.ai exchanges auth code + code_verifier for access token at /token
10. Claude.ai sends MCP requests with Authorization: Bearer <token>
```

**Claude.ai specifics:**
- Claude.ai's callback URL: `https://claude.ai/api/mcp/auth_callback` (may change to `https://claude.com/api/mcp/auth_callback`)
- User enters: Remote MCP Server URL + OAuth Client ID + OAuth Client Secret in connector dialog
- Claude.ai supports both DCR and pre-registered clients. Since D-07 implements our own OAuth server, we use pre-registered clients (admin creates client ID/secret via CLI or config)

### Pattern 2: MCP Endpoint Protection via Symfony Firewall

**What:** The MCP endpoint (`/_mcp`) is protected by a Symfony firewall that validates OAuth bearer tokens. The OAuth endpoints (`/authorize`, `/token`) are on separate firewalls.

**When to use:** All requests to the MCP endpoint.

**Configuration concept:**
```yaml
# security.yaml (installed by bundle)
security:
    firewalls:
        # OAuth token endpoint - public (clients exchange codes for tokens)
        oauth_token:
            pattern: ^/token$
            security: false

        # OAuth authorization endpoint - requires Sulu admin login
        oauth_authorize:
            pattern: ^/authorize$
            # Uses Sulu's admin firewall or a dedicated one

        # Well-known metadata - public
        well_known:
            pattern: ^/\.well-known/
            security: false

        # MCP endpoint - OAuth bearer token required
        mcp:
            pattern: ^/_mcp
            oauth2: true  # league/oauth2-server-bundle authenticator
```

### Pattern 3: Permission-First Tool Execution

**What:** Every MCP tool call passes through `PermissionGuard` before executing any Sulu service call.

**When to use:** Every tool handler method.

**Example:**
```php
class PermissionGuard
{
    public function __construct(
        private readonly SecurityCheckerInterface $securityChecker,
    ) {}

    public function checkView(string $securityContext, string $webspace, string $locale): void
    {
        // Maps to Sulu's permission system: sulu.webspaces.{webspace}.{context}
        if (!$this->securityChecker->hasPermission(
            "sulu.webspaces.{$webspace}.{$securityContext}",
            PermissionTypes::VIEW,
            $locale
        )) {
            throw new PermissionDeniedException($securityContext, $webspace, $locale, PermissionTypes::VIEW);
        }
    }
}
```

### Pattern 4: Structured MCP Error Responses

**What:** All errors return MCP-spec-compliant JSON-RPC error objects with structured `data` fields.

**When to use:** Permission denied, validation failure, entity not found.

**Format (follows JSON-RPC 2.0 error):**
```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "error": {
        "code": -32603,
        "message": "Permission denied",
        "data": {
            "type": "permission_denied",
            "detail": "User does not have 'add' permission for pages in webspace 'example'",
            "required_permission": "sulu.webspaces.example.pages.add",
            "webspace": "example",
            "locale": "en"
        }
    }
}
```

### Pattern 5: Webspace and Locale Validation

**What:** Every tool validates `webspace` and `locale` parameters against Sulu's WebspaceManager before proceeding.

**When to use:** Every content-related tool.

```php
private function validateContext(string $webspace, string $locale): void
{
    $ws = $this->webspaceManager->findWebspaceByKey($webspace);
    if ($ws === null) {
        throw new InvalidArgumentException("Unknown webspace: '{$webspace}'");
    }

    $locales = array_map(fn($l) => $l->getLocale(), $ws->getAllLocalizations());
    if (!in_array($locale, $locales, true)) {
        throw new InvalidArgumentException(
            "Locale '{$locale}' is not available in webspace '{$webspace}'. Available: " . implode(', ', $locales)
        );
    }
}
```

### Anti-Patterns to Avoid

- **Implementing OAuth from scratch:** Use league/oauth2-server. OAuth has edge cases (PKCE, token rotation, redirect URI validation) that are easy to get wrong.
- **Storing session auth in MCP session:** Resolve user from Bearer token on EVERY request. The MCP session manages protocol state, not auth state.
- **Skipping Protected Resource Metadata:** The MCP draft spec makes RFC 9728 PRM mandatory for MCP servers. Without `/.well-known/oauth-protected-resource`, Claude.ai cannot discover the authorization server.
- **Single catch-all firewall:** OAuth endpoints (`/authorize`, `/token`) and MCP endpoint (`/_mcp`) need different security configurations.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth 2.0 server | Custom token/authorize endpoints | `league/oauth2-server-bundle` | PKCE, token rotation, refresh tokens, Symfony Security integration, client management CLI. Thousands of edge cases. |
| MCP transport | Custom JSON-RPC + SSE handler | `symfony/mcp-bundle` | Session management, Mcp-Session-Id headers, capability negotiation, transport lifecycle. |
| JSON-RPC 2.0 parsing | Custom request/response parsing | `mcp/sdk` (via symfony/mcp-bundle) | Batch requests, error codes, notification handling, spec compliance. |
| Bearer token validation | Custom middleware | league/oauth2-server-bundle's Symfony authenticator | Token introspection, expiry checking, scope validation, Symfony Security passport. |
| PKCE | Custom code_challenge/code_verifier | `league/oauth2-server` (built-in) | S256 hashing, verifier validation, public client support. |

**Key insight:** The OAuth + MCP combination means two complex protocol stacks. Both have well-maintained PHP implementations. Building either from scratch would be a multi-week effort prone to security vulnerabilities.

## Common Pitfalls

### Pitfall 1: Missing Protected Resource Metadata Endpoint
**What goes wrong:** Claude.ai cannot discover the OAuth authorization server and fails to authenticate. The MCP connection silently fails or shows a generic error.
**Why it happens:** The MCP draft spec made RFC 9728 (Protected Resource Metadata) mandatory (`MUST`) for MCP servers. The 2025-03-26 spec only required RFC 8414 (Authorization Server Metadata). Many tutorials and examples skip PRM.
**How to avoid:** Implement `/.well-known/oauth-protected-resource` as a JSON endpoint returning `resource`, `authorization_servers`, and `scopes_supported`. Also implement `/.well-known/oauth-authorization-server` for auth server metadata discovery.
**Warning signs:** 401 responses with no subsequent OAuth flow initiation from the client.

### Pitfall 2: OAuth Redirect URI Mismatch with Claude.ai
**What goes wrong:** The OAuth authorization code flow completes login but the redirect back to Claude.ai fails because the redirect URI was not pre-registered or doesn't match exactly.
**Why it happens:** Claude.ai's callback URL is `https://claude.ai/api/mcp/auth_callback`. If the OAuth client is not configured with this exact redirect URI, league/oauth2-server will reject it (as required by OAuth 2.1).
**How to avoid:** When creating the OAuth client (via CLI or config), include Claude.ai's callback URL in the allowed redirect URIs. Also account for the potential future change to `https://claude.com/api/mcp/auth_callback`.
**Warning signs:** OAuth error `invalid_redirect_uri` after user approves access.

### Pitfall 3: PKCE Not Supported or Not Enforced
**What goes wrong:** Claude.ai (which is a public client) sends a `code_challenge` parameter in the authorization request. If the server doesn't support PKCE or doesn't validate the `code_verifier` on token exchange, the flow either fails or is insecure.
**Why it happens:** league/oauth2-server v9 requires PKCE for public clients by default (`requireCodeChallengeForPublicClients` = true). If the OAuth client is registered as a confidential client but Claude.ai sends PKCE params anyway, there could be a mismatch.
**How to avoid:** Ensure the OAuth client for Claude.ai is configured to support PKCE. league/oauth2-server handles this correctly by default. Verify `code_challenge_methods_supported: ["S256"]` is in the authorization server metadata.
**Warning signs:** Token exchange fails with "invalid code_verifier" or "code_challenge required".

### Pitfall 4: MCP Session Lost on PHP-FPM Worker Recycling
**What goes wrong:** PHP-FPM recycles worker processes (via `pm.max_requests`), destroying in-memory MCP sessions. The AI client gets "session not found" errors.
**Why it happens:** symfony/mcp-bundle defaults to file-based sessions (`store: file`), which survives worker recycling. But if someone changes to `store: memory`, sessions are lost.
**How to avoid:** Use `store: file` (default) or a PSR-16 cache (Redis) for production. Never use in-memory sessions with PHP-FPM. Set TTL appropriate for AI interaction duration (2-4 hours).
**Warning signs:** Intermittent "session not found" errors during long AI interactions.

### Pitfall 5: OAuth Token Not Mapping to Sulu User Permissions
**What goes wrong:** The OAuth token is valid but the authenticated user in SecurityContext doesn't have the Sulu permissions expected. All permission checks fail or all pass (if using admin).
**Why it happens:** league/oauth2-server-bundle resolves a "user" from the token, but this user must be a Sulu User entity with roles and permissions. If the bridge between OAuth user and Sulu user is not implemented correctly, the security context is wrong.
**How to avoid:** Implement the league/oauth2-server `UserEntityInterface` to load the actual Sulu User from the token's `sub` claim. Ensure the Sulu User is set in the security token (not a generic OAuth user).
**Warning signs:** Permission checks passing for restricted users, or failing for admin users.

### Pitfall 6: FPM Worker Pool Exhaustion from SSE Connections
**What goes wrong:** Streamable HTTP can optionally use SSE for server-to-client streaming. If SSE connections are held open, they tie up PHP-FPM workers.
**Why it happens:** Each SSE connection holds a worker for the connection duration. With a default pool of 5-10 workers, a few AI sessions can block all HTTP traffic.
**How to avoid:** For Phase 1, ensure the transport uses standard HTTP POST/response (not persistent SSE). The `symfony/mcp-bundle` Streamable HTTP transport returns JSON responses for tool calls. SSE is only used if the server initiates streaming, which is optional and not needed for Phase 1.
**Warning signs:** Sulu admin panel becomes unresponsive during AI sessions; 502/504 errors.

## Code Examples

### MCP Bundle Configuration (Phase 1)

```yaml
# config/packages/mcp.yaml
mcp:
    app: 'sulu-mcp-server'
    version: '1.0.0'
    description: 'Sulu CMS MCP Server - AI content management'
    client_transports:
        http: true
        stdio: false
    http:
        path: /_mcp
        session:
            store: file
            ttl: 7200  # 2 hours for AI interaction sessions
```

### Protected Resource Metadata (RFC 9728)

```php
// Controller/WellKnownController.php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class WellKnownController
{
    #[Route('/.well-known/oauth-protected-resource', methods: ['GET'])]
    public function protectedResourceMetadata(): JsonResponse
    {
        // RFC 9728 - tells MCP clients where the authorization server is
        return new JsonResponse([
            'resource' => 'https://sulu.example.com/_mcp',
            'authorization_servers' => ['https://sulu.example.com'],
            'scopes_supported' => ['mcp:tools', 'mcp:resources'],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    #[Route('/.well-known/oauth-authorization-server', methods: ['GET'])]
    public function authorizationServerMetadata(): JsonResponse
    {
        // RFC 8414 - tells MCP clients about OAuth endpoints
        return new JsonResponse([
            'issuer' => 'https://sulu.example.com',
            'authorization_endpoint' => 'https://sulu.example.com/authorize',
            'token_endpoint' => 'https://sulu.example.com/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'scopes_supported' => ['mcp:tools', 'mcp:resources'],
        ]);
    }
}
```

### Smoke-Test MCP Tool (Phase 1)

```php
// Tool/PingTool.php
namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\McpServerBundle\Security\PermissionGuard;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class PingTool
{
    public function __construct(
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {}

    #[McpTool(
        name: 'sulu_ping',
        description: 'Verify MCP connection and authentication. Returns server info and available webspaces.',
    )]
    public function ping(string $webspace, string $locale): array
    {
        // Validates webspace/locale exist (no permission check needed for ping)
        $ws = $this->webspaceManager->findWebspaceByKey($webspace);
        if ($ws === null) {
            return ['error' => true, 'code' => 'INVALID_WEBSPACE', 'message' => "Unknown webspace: '{$webspace}'"];
        }

        return [
            'status' => 'ok',
            'server' => 'sulu-mcp-server',
            'version' => '1.0.0',
            'webspace' => $webspace,
            'locale' => $locale,
            'available_locales' => array_map(fn($l) => $l->getLocale(), $ws->getAllLocalizations()),
        ];
    }
}
```

### Permission Guard

```php
// Security/PermissionGuard.php
namespace Sulu\McpServerBundle\Security;

use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;

class PermissionGuard
{
    public function __construct(
        private readonly SecurityCheckerInterface $securityChecker,
    ) {}

    /**
     * @throws PermissionDeniedException
     */
    public function check(string $securityContext, int $permissionType, ?string $locale = null): void
    {
        if (!$this->securityChecker->hasPermission($securityContext, $permissionType, $locale)) {
            throw new PermissionDeniedException($securityContext, $permissionType, $locale);
        }
    }

    public function checkPageView(string $webspace, string $locale): void
    {
        $this->check("sulu.webspaces.{$webspace}", PermissionTypes::VIEW, $locale);
    }

    public function checkPageCreate(string $webspace, string $locale): void
    {
        $this->check("sulu.webspaces.{$webspace}", PermissionTypes::ADD, $locale);
    }
}
```

### 401 Response with WWW-Authenticate Header

```php
// EventListener/McpAuthenticationListener.php
// Ensures 401 responses include the required WWW-Authenticate header with resource_metadata
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\JsonResponse;

class McpAuthenticationListener
{
    public function __construct(
        private readonly string $serverBaseUrl,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AuthenticationException) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_mcp')) {
            return;
        }

        $response = new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'Unauthorized',
            ],
            'id' => null,
        ], 401);

        $prmUrl = rtrim($this->serverBaseUrl, '/') . '/.well-known/oauth-protected-resource';
        $response->headers->set(
            'WWW-Authenticate',
            sprintf('Bearer resource_metadata="%s", scope="mcp:tools mcp:resources"', $prmUrl)
        );

        $event->setResponse($response);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| HTTP+SSE transport | Streamable HTTP transport | MCP spec 2025-03-26 | Single endpoint, stateless-capable, better load balancing |
| RFC 8414 only for auth discovery | RFC 9728 (PRM) + RFC 8414 | MCP draft spec (post 2025-03-26) | MCP servers MUST implement PRM. Two well-known endpoints needed. |
| Bearer token (simple) | OAuth 2.0 authorization code + PKCE | MCP draft spec | Required for Claude.ai connector. PKCE mandatory for all clients. |
| trikoder/oauth2-bundle | league/oauth2-server-bundle | 2024 | Same maintainers, better Symfony integration, maintained. |
| Dynamic Client Registration (recommended) | Client ID Metadata Documents (recommended), DCR (fallback) | MCP draft spec | Spec now prefers CIMD over DCR. For our case, pre-registration is fine since Claude.ai supports manual client ID/secret. |

**Deprecated/outdated:**
- **HTTP+SSE transport**: Deprecated in MCP spec 2025-03-26. Use Streamable HTTP.
- **trikoder/oauth2-bundle**: Replaced by league/oauth2-server-bundle.
- **Simple Bearer token without OAuth flow**: Claude.ai requires full OAuth authorization code flow.

## OAuth 2.0 Implementation Details

This section consolidates the OAuth research since it is the most complex part of Phase 1.

### What the MCP Spec Requires

The MCP authorization spec (draft) requires:

1. **Protected Resource Metadata (RFC 9728)** -- `MUST` for MCP servers
   - Endpoint: `/.well-known/oauth-protected-resource` (relative to server root)
   - Returns: `resource`, `authorization_servers`, `scopes_supported`

2. **Authorization Server Metadata (RFC 8414)** -- `MUST` provide at least one discovery mechanism
   - Endpoint: `/.well-known/oauth-authorization-server`
   - Returns: `issuer`, `authorization_endpoint`, `token_endpoint`, `code_challenge_methods_supported`, etc.

3. **Authorization Code Grant with PKCE** -- `MUST` for user-facing auth
   - PKCE `REQUIRED` for all clients
   - `S256` code challenge method `REQUIRED`
   - Redirect URI validation `REQUIRED`

4. **401 Unauthorized with WWW-Authenticate** -- `MUST` on unauthenticated requests
   - Header: `WWW-Authenticate: Bearer resource_metadata="<PRM URL>"`
   - Optionally includes `scope` parameter

5. **Bearer token in Authorization header** -- `MUST` on every request
   - `Authorization: Bearer <access-token>`
   - Token validated per OAuth 2.1 Section 5.2

### What Claude.ai Expects

Claude.ai's custom connector dialog provides:
- **Remote MCP Server URL** field (e.g., `https://sulu.example.com/_mcp`)
- **OAuth Client ID** field (optional, for pre-registered clients)
- **OAuth Client Secret** field (optional, for confidential clients)
- **Callback URL**: `https://claude.ai/api/mcp/auth_callback`

Claude.ai supports:
- Dynamic Client Registration (DCR)
- Pre-registered client credentials (user enters client ID + secret)
- Both Streamable HTTP and SSE transports

For our implementation: Users will create an OAuth client (via Symfony CLI command from league/oauth2-server-bundle) and enter the client ID + secret in Claude.ai's connector settings. This is the pre-registration path.

### league/oauth2-server-bundle Integration

The bundle provides:
- **CLI commands**: `league:oauth2-server:create-client`, `league:oauth2-server:update-client`, `league:oauth2-server:delete-client`, `league:oauth2-server:list-clients`
- **Symfony Security authenticator**: Validates Bearer tokens on protected firewalls (`oauth2: true`)
- **Configurable grants**: Enable auth code grant via `enable_auth_code_grant: true`
- **PKCE**: Built-in via league/oauth2-server v9 (`requireCodeChallengeForPublicClients: true` by default)
- **Scope-based roles**: After authentication, user gets `ROLE_OAUTH2_<scope>` roles
- **Token maintenance**: `league:oauth2-server:clear-expired-tokens` command

### Authorization Endpoint (Custom)

The league/oauth2-server-bundle handles the `/token` endpoint automatically but the `/authorize` endpoint must be implemented by us because:
1. We need to render the Sulu admin login page
2. We need a consent screen for MCP access approval
3. We need to map the authenticated Sulu user to the OAuth user entity

This is standard for league/oauth2-server -- the authorize endpoint is always application-specific because it involves user authentication UI.

### Scopes

For v1, two scopes:
- `mcp:tools` -- access to MCP tool calls (read + write operations)
- `mcp:resources` -- access to MCP resource reads

These are simple and sufficient. Fine-grained per-tool scopes are not needed because Sulu's permission system handles authorization at the operation level.

## Open Questions

1. **OAuth client registration mechanism**
   - What we know: league/oauth2-server-bundle provides CLI commands for client management. Config-file-based clients are also possible.
   - What's unclear: Should Phase 1 support config-based clients only (simpler, defined in YAML), or database-persisted clients (more flexible, uses Doctrine entities)?
   - Recommendation: Start with database-persisted clients (league/oauth2-server-bundle's default). Use the CLI to create clients. This is simpler than building a config-based alternative and allows future admin UI.

2. **OAuth token format**
   - What we know: league/oauth2-server generates JWT access tokens by default. Sulu user ID is embedded in the `sub` claim.
   - What's unclear: Should we use JWTs (self-contained, verifiable without DB lookup) or opaque tokens (require introspection/DB lookup)?
   - Recommendation: Use JWTs (league default). They contain the user ID, scopes, and expiry. No additional DB query needed per MCP request.

3. **Consent screen design**
   - What we know: The OAuth authorize endpoint needs to show a consent screen after Sulu login. The user approves MCP access.
   - What's unclear: How elaborate should the consent screen be? Just a simple "Allow MCP access?" or list specific scopes?
   - Recommendation: Minimal consent screen for v1 -- show the client name, requested scopes, and an "Approve" / "Deny" button. Can be enhanced later.

4. **OAuth token expiry policy**
   - What we know: JWT access tokens should be short-lived. Refresh tokens enable re-authentication.
   - What's unclear: Exact TTL values.
   - Recommendation: Access tokens: 1 hour. Refresh tokens: 30 days. Token rotation on refresh (league default).

5. **Server base URL configuration**
   - What we know: The well-known endpoints and PRM document need the server's public URL. Sulu knows its URL from webspace configuration.
   - What's unclear: How to reliably determine the server's public base URL for the `resource` and `issuer` fields.
   - Recommendation: Add a `server_url` config option to the bundle. Fall back to Sulu's webspace URL detection if not set.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Runtime | Yes | 8.4.15 | -- |
| Composer | Package management | Yes | 2.9.5 | -- |
| Node.js | Not required for Phase 1 | Yes | 22.15.0 | -- |
| Sulu CMS 3.x | Target platform | N/A (bundle will be installed INTO a Sulu project) | -- | -- |
| Symfony 7.3+ | symfony/mcp-bundle requirement | N/A (part of target Sulu project) | -- | -- |

**Note:** This is a library bundle, not a standalone application. The actual Sulu project environment will provide the runtime dependencies. PHP 8.4 on the development machine exceeds the ^8.2 requirement.

## Sources

### Primary (HIGH confidence)
- [MCP Authorization Specification (draft)](https://modelcontextprotocol.io/specification/draft/basic/authorization) -- Complete OAuth flow, PRM, metadata discovery, PKCE requirements
- [MCP Authorization Tutorial](https://modelcontextprotocol.io/docs/tutorials/security/authorization) -- Step-by-step implementation guide with code examples
- [symfony/mcp-bundle Documentation](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html) -- Complete config reference, attributes, transport setup
- [symfony/mcp-bundle on Packagist](https://packagist.org/packages/symfony/mcp-bundle) -- v0.6.0, Symfony ^7.3|^8.0
- [mcp/sdk on Packagist](https://packagist.org/packages/mcp/sdk) -- v0.4.0, PHP ^8.1
- [league/oauth2-server-bundle on Packagist](https://packagist.org/packages/league/oauth2-server-bundle) -- v1.1.1, Symfony ^6.4|^7.0|^8.0
- [league/oauth2-server-bundle Basic Setup](https://github.com/thephpleague/oauth2-server-bundle/blob/master/docs/basic-setup.md) -- Firewall config, client management CLI, scope-based roles
- [league/oauth2-server Auth Code Grant](https://oauth2.thephpleague.com/authorization-server/auth-code-grant/) -- PKCE implementation details

### Secondary (MEDIUM confidence)
- [Claude.ai Custom Connectors Guide](https://support.claude.com/en/articles/11503834-building-custom-connectors-via-remote-mcp-servers) -- OAuth callback URL, DCR support, client registration
- [Claude.ai Getting Started with Connectors](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp) -- Connector setup flow
- [MCP Spec 2025-03-26 Authorization](https://modelcontextprotocol.io/specification/2025-03-26/basic/authorization) -- Earlier spec version for comparison
- [RFC 9728 - Protected Resource Metadata](https://datatracker.ietf.org/doc/html/rfc9728) -- PRM specification
- [RFC 8414 - Authorization Server Metadata](https://datatracker.ietf.org/doc/html/rfc8414) -- AS metadata specification

### Tertiary (LOW confidence)
- [Claude.ai MCP OAuth Issue #112](https://github.com/anthropics/claude-ai-mcp/issues/112) -- Discussion about Bearer token vs OAuth-only in Claude.ai connector

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all packages verified on Packagist with current versions and compatibility confirmed
- Architecture: HIGH -- OAuth flow verified against MCP draft spec and Claude.ai documentation; symfony/mcp-bundle config verified from official docs
- Pitfalls: HIGH -- based on MCP spec requirements, OAuth standards, and PHP-FPM behavior

**Research date:** 2026-03-29
**Valid until:** 2026-04-15 (MCP spec is draft status; auth requirements may evolve)

---
*Phase: 01-bundle-foundation-transport*
*Research completed: 2026-03-29*
