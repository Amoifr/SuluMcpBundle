# Development App

A Sulu 3.x application for developing and manually testing the
[Sulu MCP Bundle](../README.md). The bundle is symlinked into `vendor/` via a
composer path repository, so changes in `../src` are picked up immediately.

## Setup

```bash
composer install
bin/mcp-setup-info
```

`bin/mcp-setup-info` prints the remaining steps: generating the OAuth keypair,
updating the database schema, loading fixtures, and registering an MCP client.

Automated tests live in the bundle itself (`../tests`), not in this app.
