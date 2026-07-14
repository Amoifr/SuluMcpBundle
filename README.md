# Sulu MCP Server

MCP server for [Sulu CMS](https://sulu.io) 3.x — let AI assistants manage your Sulu content.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Symfony](https://img.shields.io/badge/symfony-%5E7.3-000.svg)](composer.json)
[![Sulu](https://img.shields.io/badge/sulu-%5E3.0-52b6ca.svg)](composer.json)

A Symfony bundle that exposes Sulu's content management as [Model Context Protocol](https://modelcontextprotocol.io) tools over Streamable HTTP. AI assistants like Claude.ai, ChatGPT, and Cursor can create pages, edit articles, manage media, and publish content — using the authenticated Sulu user's existing roles and permissions. No separate auth, no privilege escalation.

## Requirements

- PHP `^8.2`
- Symfony `^7.3`
- Sulu `^3.0`

## Installation

```bash
composer require sulu/mcp-server-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Sulu\McpServerBundle\SuluMcpServerBundle::class => ['all' => true],
];
```

Import the routes in `config/routes.yaml`:

```yaml
sulu_mcp_server:
    resource: '@SuluMcpServerBundle/config/routes.yaml'
```

Set the public server URL in your environment:

```bash
SULU_MCP_SERVER_URL=https://your-sulu-host.example.com
```

## Configuration

```yaml
# config/packages/sulu_mcp_server.yaml
sulu_mcp_server:
    server_url: '%env(SULU_MCP_SERVER_URL)%'
    dangerous_tools:
        delete: false        # sulu_*_delete (page, article, tag, category)
        publish: false       # sulu_*_publish, sulu_*_unpublish, sulu_preview_link_revoke
        block_remove: false  # sulu_block_remove
```

All three `dangerous_tools` flags default to `false`. Enable per category to expose those tools to MCP clients. Full reference: [`docs/configuration.md`](docs/configuration.md).

## Tools

37 MCP tools, grouped by domain:

| Domain | Count | Examples |
|--------|-------|----------|
| Pages | 5 | `sulu_page_create`, `sulu_page_get`, `sulu_page_list`, `sulu_page_tree`, `sulu_page_update` |
| Blocks | 5 | `sulu_block_add`, `sulu_block_update`, `sulu_block_reorder`, `sulu_block_list`, `sulu_block_remove` — generic over page/article/snippet via a `type` param |
| Articles | 4 | `sulu_article_create`, `sulu_article_update`, `sulu_article_get`, `sulu_article_list` |
| Snippets | 4 | `sulu_snippet_create`, `sulu_snippet_update`, `sulu_snippet_get`, `sulu_snippet_list` |
| Unified content | 3 | `sulu_content_delete`, `sulu_content_publish`, `sulu_content_unpublish` — take a `type` param (`page` \| `article` \| `snippet`) |
| Media | 3 | `sulu_media_list`, `sulu_media_get`, `sulu_media_update` |
| Taxonomy | 6 | `sulu_tag_*`, `sulu_category_*` |
| Navigation | 1 | `sulu_navigation_get` |
| Preview | 2 | `sulu_preview_link_generate`, `sulu_preview_link_revoke` |
| Contact | 1 | `sulu_contact_list` |
| Misc | 3 | `sulu_content_search`, `sulu_get_context`, `sulu_ping` |

Tools in the **delete**, **publish**, and **block_remove** categories are gated by the `dangerous_tools` config above.

## Connecting an MCP client

The MCP endpoint defaults to `/admin/_mcp`. Authentication uses Sulu's user system via OAuth 2.1 (Dynamic Client Registration supported through `league/oauth2-server-bundle`).
During OAuth authorization, Sulu opens the admin login when needed and then shows an explicit consent screen before the MCP client receives tokens.

Per-client setup guides:

- [Claude.ai](docs/clients/claude-ai.md)
- [Claude Code](docs/clients/claude-code.md)
- [Claude Cowork](docs/clients/claude-cowork.md)
- [ChatGPT](docs/clients/chatgpt.md)
- [Codex](docs/clients/codex.md)

For the recommended system prompt to give your AI assistant when working with Sulu content, see [`docs/CONTENT_ASSISTANT_PROMPT.md`](docs/CONTENT_ASSISTANT_PROMPT.md). The full docs index lives at [`docs/README.md`](docs/README.md).

## Development

```bash
composer fix     # rector + php-cs-fixer
composer lint    # phpstan + cs check + rector dry-run + composer validate
composer test    # phpunit
```

Run in that order. See [`AGENTS.md`](AGENTS.md) for contributor guidelines.

## License

[MIT](LICENSE).
