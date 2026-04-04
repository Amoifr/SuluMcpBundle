---
phase: 03-page-content-management
plan: 03
subsystem: api
tags: [mcp-tools, blocks, read-modify-write, symfony-messenger]

requires:
  - phase: 03-01
    provides: "PageGetTool read pattern (PageRepositoryInterface + ContentManagerInterface)"
  - phase: 03-02
    provides: "PageUpdateTool write pattern (HandleTrait + ModifyPageMessage)"
provides:
  - "BlockAddTool — add block by type with position support"
  - "BlockRemoveTool — remove block by index with bounds validation"
  - "BlockReorderTool — reorder blocks with newOrder array validation"
affects: [03-04, phase-04]

tech-stack:
  added: []
  patterns: ["read-modify-write for block mutations via ContentManager + ModifyPageMessage"]

key-files:
  created:
    - src/Tool/BlockAddTool.php
    - src/Tool/BlockRemoveTool.php
    - src/Tool/BlockReorderTool.php
    - tests/Unit/Tool/BlockAddToolTest.php
    - tests/Unit/Tool/BlockRemoveToolTest.php
    - tests/Unit/Tool/BlockReorderToolTest.php
  modified:
    - config/services.yaml

key-decisions:
  - "blockProperty parameter instead of hardcoded property name — supports any template block configuration"
  - "Error responses for invalid indices/order rather than exceptions — consistent with other tool error handling"

patterns-established:
  - "Read-modify-write for content mutations: read via ContentManager, modify array, dispatch ModifyPageMessage"
  - "Preserve template and title from currentData when dispatching ModifyPageMessage to avoid clearing fields"

requirements-completed: [BLCK-01, BLCK-02, BLCK-03]

duration: 4min
completed: 2026-03-30
---

# Phase 03 Plan 03: Block Management Tools Summary

**Three block tools (add, remove, reorder) using read-modify-write pattern with parameterized block property and input validation**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-30T20:46:28Z
- **Completed:** 2026-03-30T20:50:37Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- BlockAddTool with append-or-insert-at-position logic and blockData merge
- BlockRemoveTool with bounds-checking error responses for invalid indices
- BlockReorderTool with full validation (length check, duplicate check, range check)
- 21 unit tests covering all block tools with mocked read-modify-write cycle

## Task Commits

Each task was committed atomically:

1. **Task 1: Implement BlockAddTool, BlockRemoveTool, BlockReorderTool** - `0dec921` (feat)
2. **Task 2: Unit tests for block tools** - `134fb6c` (test)

## Files Created/Modified
- `src/Tool/BlockAddTool.php` - Add block to page with position support
- `src/Tool/BlockRemoveTool.php` - Remove block by index with bounds validation
- `src/Tool/BlockReorderTool.php` - Reorder blocks with newOrder array validation
- `tests/Unit/Tool/BlockAddToolTest.php` - 8 tests for add block scenarios
- `tests/Unit/Tool/BlockRemoveToolTest.php` - 6 tests for remove block scenarios
- `tests/Unit/Tool/BlockReorderToolTest.php` - 7 tests for reorder block scenarios
- `config/services.yaml` - Service registrations for 3 block tools

## Decisions Made
- Used parameterized `blockProperty` parameter instead of hardcoding "blocks" -- Sulu templates can use any property name for block content
- Return error arrays for invalid inputs (out-of-range index, bad reorder array) rather than throwing exceptions -- consistent with PageGetTool/PageUpdateTool error handling pattern
- Preserve `template` and `title` from current page data when dispatching ModifyPageMessage to prevent accidental field clearing

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed redundant array_values call in BlockReorderTool**
- **Found during:** Task 1
- **Issue:** PHPStan flagged `array_values()` on a `list<>` type as having no effect
- **Fix:** Removed the redundant `array_values()` wrapper around `array_map()` result
- **Files modified:** src/Tool/BlockReorderTool.php
- **Verification:** `composer phpstan` exits 0
- **Committed in:** 0dec921

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Minor PHPStan compliance fix. No scope creep.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Block management tools complete, ready for Plan 04 (publishing workflow)
- All page CRUD + block operations now available for AI clients

---
*Phase: 03-page-content-management*
*Completed: 2026-03-30*
