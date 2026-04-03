---
phase: quick
plan: 260403-r6h
subsystem: tools
tags: [mcp, preview, sulu-preview-bundle]

requires:
  - phase: 01-foundation
    provides: MCP tool infrastructure and patterns
provides:
  - sulu_preview_link_generate MCP tool
  - sulu_preview_link_revoke MCP tool
affects: []

tech-stack:
  added: []
  patterns:
    - PreviewLinkManagerInterface delegation for preview link operations

key-files:
  created:
    - src/Tool/PreviewLinkGenerateTool.php
    - src/Tool/PreviewLinkRevokeTool.php
    - tests/Unit/Tool/PreviewLinkGenerateToolTest.php
    - tests/Unit/Tool/PreviewLinkRevokeToolTest.php
  modified:
    - config/services.yaml

key-decisions:
  - "No webspace required for articles, only for pages (matches Sulu PreviewRenderer behavior)"

patterns-established: []

requirements-completed: []

duration: 3min
completed: 2026-04-03
---

# Quick Task 260403-r6h: Preview Link Tools Summary

**MCP tools wrapping Sulu PreviewLinkManagerInterface to generate and revoke shareable public preview URLs for draft pages/articles**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-03T08:38:24Z
- **Completed:** 2026-04-03T08:41:07Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- PreviewLinkGenerateTool generates absolute public preview URLs via RouterInterface, passing webspaceKey in options for pages
- PreviewLinkRevokeTool invalidates existing preview links
- Full unit test coverage (8 tests, 33 assertions) covering success paths, error handling, and attribute verification

## Task Commits

Each task was committed atomically:

1. **Task 1: Create PreviewLinkGenerateTool and PreviewLinkRevokeTool** - `dcdf62f` (feat)
2. **Task 2: Register tools in services.yaml and run quality checks** - `080b3ac` (chore)

## Files Created/Modified
- `src/Tool/PreviewLinkGenerateTool.php` - MCP tool wrapping PreviewLinkManagerInterface::generate() with RouterInterface for URL generation
- `src/Tool/PreviewLinkRevokeTool.php` - MCP tool wrapping PreviewLinkManagerInterface::revoke()
- `tests/Unit/Tool/PreviewLinkGenerateToolTest.php` - 5 tests: page generation, article generation, webspace options, error handling, attribute check
- `tests/Unit/Tool/PreviewLinkRevokeToolTest.php` - 3 tests: success, error handling, attribute check
- `config/services.yaml` - Service registrations for both tools

## Decisions Made
- No webspace required for articles -- only pages need webspaceKey in options (matches Sulu PreviewRenderer internals)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## Verification

- composer fix: passed
- composer lint: passed
- composer phpstan: passed (no errors)
- composer test: passed (238 tests, 721 assertions, 1 skipped)

---
*Quick task: 260403-r6h*
*Completed: 2026-04-03*
