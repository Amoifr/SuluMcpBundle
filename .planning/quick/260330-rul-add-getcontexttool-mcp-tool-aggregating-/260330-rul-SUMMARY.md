---
phase: quick
plan: 260330-rul
subsystem: mcp-tools
tags: [tool, context, aggregation, mcp, tdd]
dependency_graph:
  requires: [TemplatesResource, BlocksResource, WebspacesResource, SitemapResource, GuidelinesResource, CompanyContextResource]
  provides: [GetContextTool, sulu_get_context MCP tool]
  affects: [config/services.yaml]
tech_stack:
  added: []
  patterns: [McpTool attribute, constructor injection, graceful exception handling]
key_files:
  created:
    - src/Tool/GetContextTool.php
    - tests/Unit/Tool/GetContextToolTest.php
  modified:
    - config/services.yaml
decisions:
  - "Catch InvalidArgumentException from SitemapResource and return null rather than propagating the error — keeps the tool ergonomic for AI clients calling with arbitrary webspace keys"
metrics:
  duration: 5min
  completed: 2026-03-30
  tasks_completed: 1
  files_changed: 3
---

# Quick Task 260330-rul: Add GetContextTool MCP Tool Summary

**One-liner:** `sulu_get_context` MCP tool aggregating all six CMS context resources (templates, blocks, webspaces, guidelines, company context, sitemap) into a single round-trip call with graceful sitemap fallback.

## What Was Built

`GetContextTool` is a new MCP tool registered as `sulu_get_context`. An AI client (Claude, ChatGPT) calls this single tool before creating or editing content, receiving the full CMS context in one response instead of reading six separate resources.

The tool:
- Injects all six existing resource classes via constructor
- Delegates each call to the respective resource — no logic duplication
- Returns `compact('templates', 'blocks', 'webspaces', 'guidelines', 'company_context', 'sitemap')`
- Catches `\InvalidArgumentException` from `SitemapResource::getSitemap()` and returns `null` for the `sitemap` key — allows calling with any webspace key without crashing

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Implement GetContextTool with unit tests | 2b3bded | src/Tool/GetContextTool.php, tests/Unit/Tool/GetContextToolTest.php, config/services.yaml |

## Test Results

- 5 new tests added in `GetContextToolTest` (all pass)
- Full suite: 52 tests, 168 assertions — no regressions

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all six resource classes are fully wired. The tool delegates to production resource implementations.

## Self-Check: PASSED

- `src/Tool/GetContextTool.php` — exists
- `tests/Unit/Tool/GetContextToolTest.php` — exists
- `config/services.yaml` — updated with `GetContextTool` entry
- Commit `2b3bded` — verified in git log
