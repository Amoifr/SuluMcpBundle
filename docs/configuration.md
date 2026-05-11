# Configuration

All bundle configuration lives under the `sulu_mcp_server` key in `config/packages/sulu_mcp_server.yaml`. Only `server_url` is required.

## Full reference

```yaml
sulu_mcp_server:
    # REQUIRED. Public base URL of the Sulu installation. Used for OAuth issuer
    # metadata and generating absolute callback URLs.
    server_url: '%env(SULU_MCP_SERVER_URL)%'

    # MCP endpoint path. Default: /admin/_mcp
    mcp_path: '/admin/_mcp'

    oauth:
        # Access token lifetime in seconds. Default: 3600 (1 hour).
        access_token_ttl: 3600
        # Refresh token lifetime in seconds. Default: 2592000 (30 days).
        refresh_token_ttl: 2592000
        # OAuth scopes advertised by the server.
        scopes:
            - 'mcp:tools'
            - 'mcp:resources'

    # Opt-in flags for tools with hard-to-reverse side effects.
    # All categories default to false.
    dangerous_tools:
        delete: false        # sulu_*_delete (page, article, tag, category)
        publish: false       # sulu_*_publish, sulu_*_unpublish, sulu_preview_link_revoke
        block_remove: false  # sulu_block_remove, sulu_article_block_remove
```

## Settings

### `server_url` (required)

The publicly reachable base URL of your Sulu installation, e.g. `https://sulu.example.com`. The bundle uses it to advertise OAuth endpoints and to compose the MCP server URL printed by `sulu:mcp:create-client`.

Use an env var so it differs per environment:

```bash
# .env.local / .env.prod
SULU_MCP_SERVER_URL=https://sulu.example.com
```

### `mcp_path`

The HTTP path serving MCP requests. Default `/admin/_mcp`. The `/admin/...` prefix routes the request into Sulu's admin kernel via the standard front-controller mapping, so admin-context services (article preview provider, etc.) are available to the tools. Change it only if you need to avoid a route collision; keep the `/admin/` prefix unless you've explicitly routed a different path to the admin kernel. Clients must use the same path.

If you change `mcp_path`, also update the `pattern` of the `mcp` firewall in your `security.yaml` (see "Required security setup" below) and the URL registered with each MCP client.

## Required security setup

The MCP endpoint lives under `/admin/_mcp` so its requests reach the admin kernel. Sulu's standard `admin` firewall has the pattern `^/admin(\/|$)`, which also matches the MCP path -- Symfony applies the *first* firewall whose pattern matches, in declaration order. The MCP firewall therefore must be declared **before** the admin firewall in your `config/packages/security.yaml`:

```yaml
security:
    firewalls:
        # ...any "dev" or static-asset firewalls...
        mcp:
            pattern: ^/admin/_mcp
            provider: sulu                 # or whichever provider authenticates Sulu users
            stateless: true
            entry_point: Sulu\McpServerBundle\Security\EventListener\McpAuthenticationListener
            oauth2: true
        admin:
            pattern: ^/admin(\/|$)
            # ...existing admin firewall config...

    access_control:
        # Allow the OAuth discovery and token/registration endpoints through
        # without a session.
        - { path: ^/\.well-known/oauth-, roles: PUBLIC_ACCESS }
        - { path: ^/mcp/register, roles: PUBLIC_ACCESS }
        - { path: ^/mcp/token, roles: PUBLIC_ACCESS }
        # Require a valid OAuth bearer on the MCP endpoint itself.
        - { path: ^/admin/_mcp, roles: IS_AUTHENTICATED_FULLY }
        # ...your existing admin rules...
```

This setup keeps the MCP traffic stateless (no PHP session cookies), isolated from your form-login / two-factor / HTTP-basic flows on `/admin/...`, and works alongside any extra middleware your host project layers onto the admin firewall.

### `oauth.access_token_ttl` / `oauth.refresh_token_ttl`

Token lifetimes in seconds. The defaults (1 hour / 30 days) match common hosted-client expectations. Shorter access tokens reduce blast radius on leak; longer refresh tokens reduce re-login friction.

### `oauth.scopes`

The scopes the server advertises and accepts. The two defaults map to MCP semantics:

- `mcp:tools` — call tools (`tools/list`, `tools/call`).
- `mcp:resources` — read resources.

You don't normally change this. Add scopes only if you've extended the bundle with custom OAuth grants.

### `dangerous_tools.*`

Three booleans gating 11 high-impact tools. Each flag is independent — enable only what you need.

| Flag | Tools enabled when `true` |
|------|---------------------------|
| `delete` | `sulu_page_delete`, `sulu_article_delete`, `sulu_tag_delete`, `sulu_category_delete` |
| `publish` | `sulu_page_publish`, `sulu_page_unpublish`, `sulu_article_publish`, `sulu_article_unpublish`, `sulu_preview_link_revoke` |
| `block_remove` | `sulu_block_remove`, `sulu_article_block_remove` |

When a flag is `false`, the corresponding tool services are removed from the container at compile time — they don't appear in MCP `tools/list` and calls fail with "unknown tool" rather than running with an error. To change a flag, edit the YAML and clear the cache (`bin/console cache:clear`).

## Recommended profiles

**Read-only / staging** — leave `dangerous_tools` at defaults. The AI can read everything and create drafts, but cannot publish or delete.

```yaml
sulu_mcp_server:
    server_url: '%env(SULU_MCP_SERVER_URL)%'
```

**Editorial workflow** — let the AI publish but not delete:

```yaml
sulu_mcp_server:
    server_url: '%env(SULU_MCP_SERVER_URL)%'
    dangerous_tools:
        publish: true
```

**Full agent control** — only on accounts you trust to act autonomously:

```yaml
sulu_mcp_server:
    server_url: '%env(SULU_MCP_SERVER_URL)%'
    dangerous_tools:
        delete: true
        publish: true
        block_remove: true
```

## Verifying

After changing config, clear the cache and inspect the registered MCP tools:

```bash
bin/console cache:clear
bin/console debug:container --tag=mcp.tool
```

The list reflects the active `dangerous_tools` configuration.
