# Documentation

Sulu MCP Server is a Symfony bundle that turns a Sulu 3.x installation into an [MCP](https://modelcontextprotocol.io) server. AI assistants connect over HTTP and can read, create, edit, and publish content using the authenticated user's existing Sulu permissions.

## Contents

- [Configuration](configuration.md) — all bundle config options with examples.
- [Content Assistant Prompt](CONTENT_ASSISTANT_PROMPT.md) — recommended system prompt for AI clients.
- Client setup:
  - [Claude.ai](clients/claude-ai.md) — hosted web/desktop app, OAuth connector.
  - [Claude Code](clients/claude-code.md) — CLI, configured via `.mcp.json`.
  - [Claude Cowork](clients/claude-cowork.md) — collaborative workspace, OAuth connector.

## What it exposes

The bundle ships **49 tools** spanning the core Sulu domains:

- Pages, articles, snippets — full lifecycle (CRUD, publish/unpublish, blocks, SEO, excerpt).
- Media, taxonomy (tags, categories), navigation, contacts.
- Preview links, content search, and a context tool that briefs the AI on the current Sulu instance.

A handful of high-impact tools (delete, publish, block-remove) are gated behind opt-in config — see the bundle [README](../README.md#configuration).

## Transport

[Streamable HTTP](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports) — a single endpoint at `/admin/_mcp` that accepts JSON-RPC over HTTP POST and optionally streams responses with SSE. The `/admin/...` prefix routes the request into Sulu's admin kernel, where admin-context services (article preview provider, etc.) are registered. The legacy HTTP+SSE transport is not used.

## Authentication

OAuth 2.1 with Dynamic Client Registration, backed by `league/oauth2-server-bundle`. Once a client completes the OAuth flow, every MCP request runs under that Sulu user — operations the user cannot perform in the admin UI also fail via MCP.

For hosted clients (Claude.ai, Claude Cowork), create an OAuth client up front:

```bash
php bin/console sulu:mcp:create-client "Claude.ai Production"
```

The command prints the Client ID, Client Secret, and the redirect URI to paste into the client's connector setup. Claude Code uses Dynamic Client Registration and skips this step.
