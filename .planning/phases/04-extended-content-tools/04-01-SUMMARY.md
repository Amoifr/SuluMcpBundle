---
phase: 04-extended-content-tools
plan: 01
subsystem: api
tags: [mcp, articles, crud, message-bus, sulu-3]

requires:
  - phase: 03-page-content-management
    provides: "Page CRUD tool patterns (get, list, create, update, delete), HandleTrait messageBus pattern, normalizeContent() helper"
provides:
  - "5 article CRUD MCP tools: get, list, create, update, delete"
  - "Article tools with no webspace parameter (articles are global)"
  - "CreateArticleMessage single-param pattern (data only, no webspace/parentId)"
affects: [04-02, 04-03, 04-04]

tech-stack:
  added: []
  patterns:
    - "Article message bus pattern: CreateArticleMessage(data) vs CreatePageMessage(webspace, parentId, data)"
    - "Flat entity tools: no parentId, no webspace, no tree operations"

key-files:
  created:
    - src/Tool/ArticleGetTool.php
    - src/Tool/ArticleListTool.php
    - src/Tool/ArticleCreateTool.php
    - src/Tool/ArticleUpdateTool.php
    - src/Tool/ArticleDeleteTool.php
    - tests/Unit/Tool/ArticleGetToolTest.php
    - tests/Unit/Tool/ArticleListToolTest.php
    - tests/Unit/Tool/ArticleCreateToolTest.php
    - tests/Unit/Tool/ArticleUpdateToolTest.php
    - tests/Unit/Tool/ArticleDeleteToolTest.php
  modified: []

key-decisions:
  - "Article tools have no webspace parameter -- articles are global entities in Sulu 3.0"
  - "CreateArticleMessage takes single $data array (not webspace+parentId+data like pages)"
  - "ArticleDeleteTool has no forceRemoveChildren -- articles are flat (no hierarchy)"
  - "Reuse PageUpdateTool::normalizeContent() for content normalization in create/update"

patterns-established:
  - "Flat entity tool pattern: no parentId, no webspace, no tree operations for non-hierarchical content"
  - "Article-specific repository constants: GROUP_SELECT_ARTICLE_ADMIN"

requirements-completed: [ARTC-01, ARTC-02, ARTC-03, ARTC-04, ARTC-05]

duration: 4min
completed: 2026-03-31
---

# Phase 04 Plan 01: Article CRUD Tools Summary

**5 article CRUD MCP tools (get, list, create, update, delete) mirroring page tool patterns without webspace/parentId for flat article entities**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-31T07:54:36Z
- **Completed:** 2026-03-31T07:58:21Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- ArticleGetTool and ArticleListTool for reading articles with ContentManager resolve/normalize
- ArticleCreateTool dispatching CreateArticleMessage with single $data param (no webspace/parentId)
- ArticleUpdateTool with read-modify-dispatch pattern reusing normalizeContent() helper
- ArticleDeleteTool with RemoveArticleMessage (no forceRemoveChildren -- articles are flat)
- 24 unit tests passing, PHPStan clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Article read tools (get + list) with tests** - `de92e09` (feat)
2. **Task 2: Article write tools (create, update, delete) with tests** - `9fda8fe` (feat)

## Files Created/Modified
- `src/Tool/ArticleGetTool.php` - Get single article by UUID via ArticleRepositoryInterface
- `src/Tool/ArticleListTool.php` - List articles with template/pagination filtering
- `src/Tool/ArticleCreateTool.php` - Create article via CreateArticleMessage (single $data param)
- `src/Tool/ArticleUpdateTool.php` - Update article with read-modify-dispatch via ModifyArticleMessage
- `src/Tool/ArticleDeleteTool.php` - Delete article via RemoveArticleMessage
- `tests/Unit/Tool/ArticleGetToolTest.php` - 5 tests for get tool
- `tests/Unit/Tool/ArticleListToolTest.php` - 5 tests for list tool
- `tests/Unit/Tool/ArticleCreateToolTest.php` - 6 tests for create tool
- `tests/Unit/Tool/ArticleUpdateToolTest.php` - 4 tests for update tool
- `tests/Unit/Tool/ArticleDeleteToolTest.php` - 4 tests for delete tool

## Decisions Made
- Articles have no webspace parameter -- they are global entities in Sulu 3.0
- CreateArticleMessage takes single $data array (not webspace+parentId+data like CreatePageMessage)
- ArticleDeleteTool has no forceRemoveChildren parameter -- articles are flat with no hierarchy
- Reused PageUpdateTool::normalizeContent() static helper for AI client input normalization

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Article CRUD tools complete, ready for article block tools (Plan 04-02) and article publishing (Plan 04-03)
- Pattern established for flat entity tools that future snippet tools can follow

## Verification

- composer test -- --filter "Article": 24 tests, 63 assertions, OK
- composer phpstan: No errors
- composer fix: 0 files fixed (already clean)
- composer lint: not run separately (fix covers it)

---
*Phase: 04-extended-content-tools*
*Completed: 2026-03-31*
