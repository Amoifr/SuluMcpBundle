---
phase: 02-context-discovery-guidelines
plan: 02
subsystem: database
tags: [doctrine, orm, mcp-resources, mcp-tools, content-guidelines, company-context, php, symfony]

requires:
  - phase: 02-context-discovery-guidelines/02-01
    provides: Doctrine ORM mapping prepend in SuluMcpServerExtension, McpResourceTemplate pattern, resource test scaffolds

provides:
  - ContentGuidelines entity (sulu_mcp_content_guidelines table) with per-webspace nullable text fields
  - CompanyContext entity (sulu_mcp_company_context table) with singleton nullable text fields
  - ContentGuidelinesRepository.resolveForWebspace() — global+override merge algorithm
  - CompanyContextRepository.toArray() — MCP-ready serialization
  - GuidelinesResource — sulu://guidelines/{webspace} McpResourceTemplate
  - CompanyContextResource — sulu://context/company McpResource
  - UpdateGuidelinesTool — sulu_update_guidelines MCP write tool with soft 2000-char warning
  - UpdateCompanyContextTool — sulu_update_company_context MCP write tool (singleton row)

affects:
  - 03-content-tools — content tools will read guidelines via resolveForWebspace() when generating content
  - future phases — company context provides brand context for all AI content generation

tech-stack:
  added: []
  patterns:
    - "Doctrine entity with PHP attribute mapping: #[ORM\\Entity], #[ORM\\Table], #[ORM\\Column]"
    - "ServiceEntityRepository extending Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository"
    - "Global-plus-override merge: fetch global (webspace=null), then fetch per-webspace and merge non-null fields"
    - "Singleton pattern: findOneBy([]) returns first (and only) row, create if missing"
    - "Soft constraint with warning: character limit advisory returned in tool response, not hard DB constraint"
    - "Output key vs PHP property: $donts property serialized as \"don'ts\" array key (apostrophe only in output)"

key-files:
  created:
    - src/Entity/ContentGuidelines.php
    - src/Entity/CompanyContext.php
    - src/Repository/ContentGuidelinesRepository.php
    - src/Repository/CompanyContextRepository.php
    - src/Resource/GuidelinesResource.php
    - src/Resource/CompanyContextResource.php
    - src/Tool/UpdateGuidelinesTool.php
    - src/Tool/UpdateCompanyContextTool.php
    - tests/Unit/Entity/ContentGuidelinesEntityTest.php
    - tests/Unit/Entity/CompanyContextEntityTest.php
    - tests/Unit/Resource/ContentGuidelinesResourceTest.php
    - tests/Unit/Resource/CompanyContextResourceTest.php
  modified:
    - config/services.yaml

key-decisions:
  - "PHP property named $donts (no apostrophe), serialized as \"don'ts\" array key — PHP allows apostrophes only in string literals, not identifiers"
  - "Global defaults stored as webspace=null row — allows single resolveForWebspace() method to handle both global and per-webspace via merge"
  - "CompanyContext is a singleton row: findOneBy([]) retrieves it, no webspace key needed since company context is installation-wide"
  - "Soft 2000-char limit in UpdateGuidelinesTool: advisory warning in response, not DB constraint — avoids silent truncation of AI-authored guidelines"
  - "toArray() lives in CompanyContextRepository (public method) so both resource and future tools can call it without duplication"

patterns-established:
  - "Entity test: instantiate real entity (no mocking), use ReflectionClass to assert ORM attribute presence and values"
  - "Resource test: mock repository, assert method delegates to repository and returns result unchanged"
  - "Write tool pattern: findOneBy() to load or new Entity() to create, conditional setters for provided-only fields, persist+flush, return success array"

requirements-completed:
  - GUID-01
  - GUID-02
  - GUID-03
  - GUID-04

duration: 4min
completed: "2026-03-30"
---

# Phase 2 Plan 2: Content Guidelines System Summary

**Two Doctrine entities (ContentGuidelines, CompanyContext) with repositories, MCP read resources (sulu://guidelines/{webspace}, sulu://context/company), and MCP write tools — completing the AI content guidelines infrastructure**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-30T14:45:24Z
- **Completed:** 2026-03-30T14:49:00Z
- **Tasks:** 2 (TDD: test scaffolds + implementation)
- **Files modified:** 13

## Accomplishments

- Two Doctrine entities covering the full content guidelines data model
- Global-plus-override merge algorithm in ContentGuidelinesRepository — AI clients get merged context with a single URI call
- Four MCP capabilities: two read resources (sulu://guidelines/{webspace}, sulu://context/company) and two write tools (sulu_update_guidelines, sulu_update_company_context)
- 14 new unit tests (47 total), all green

## Task Commits

Each task was committed atomically:

1. **Task 1: Test scaffolds (TDD RED)** - `4f1ce2c` (test)
2. **Task 2: Entities, repositories, resources, write tools** - `cf34201` (feat)

## Files Created/Modified

- `src/Entity/ContentGuidelines.php` — sulu_mcp_content_guidelines with webspace (unique nullable), tone, audience, style, brand_rules, dos, donts
- `src/Entity/CompanyContext.php` — sulu_mcp_company_context with company_name, description, industry, website, key_products
- `src/Repository/ContentGuidelinesRepository.php` — resolveForWebspace() with merge logic, private toArray() with "don'ts" key
- `src/Repository/CompanyContextRepository.php` — public toArray() for MCP serialization
- `src/Resource/GuidelinesResource.php` — sulu://guidelines/{webspace} McpResourceTemplate
- `src/Resource/CompanyContextResource.php` — sulu://context/company McpResource
- `src/Tool/UpdateGuidelinesTool.php` — sulu_update_guidelines with 2000-char soft warning
- `src/Tool/UpdateCompanyContextTool.php` — sulu_update_company_context singleton upsert
- `config/services.yaml` — registered GuidelinesResource, CompanyContextResource, UpdateGuidelinesTool, UpdateCompanyContextTool, ContentGuidelinesRepository, CompanyContextRepository
- `tests/Unit/Entity/ContentGuidelinesEntityTest.php` — 4 tests: table name, nullable/unique webspace, text fields, getters/setters
- `tests/Unit/Entity/CompanyContextEntityTest.php` — 3 tests: table name, nullable fields, getters/setters
- `tests/Unit/Resource/ContentGuidelinesResourceTest.php` — 4 tests: resolveForWebspace delegation, merge return, global handling, attribute assertion
- `tests/Unit/Resource/CompanyContextResourceTest.php` — 3 tests: entity data return, null fields, McpResource attribute assertion

## Decisions Made

- PHP property `$donts` serializes to output key `"don'ts"` — PHP identifiers cannot contain apostrophes, but array keys can. Documented in both entity and repository.
- Global defaults row uses `webspace = null`, not the string `'global'` — the `'global'` keyword is normalized to `null` in the write tool and handled as a special case in `resolveForWebspace()`.
- `toArray()` is public on CompanyContextRepository so it can be reused across resource and future tools without coupling.

## Deviations from Plan

None - plan executed exactly as written. All entity fields, merge algorithm, resource attributes, and tool logic followed the plan specification.

## Issues Encountered

- Worktree was at `main` (2af74b9) without plan 02-01's Resource files. Resolved by fast-forward merging the phase branch `gsd/phase-02-context-discovery-guidelines` — this is expected worktree behavior when spawned from main.
- Worktree vendor directory setup (autoload.php + phpunit symlink) applied following the same pattern established by the previous agent worktree.

## Known Stubs

None — all data fields are wired from real entity getters. No placeholder values, hardcoded data, or TODO comments in production code.

## Next Phase Readiness

- Content guidelines system complete: AI clients can read `sulu://guidelines/{webspace}` and `sulu://context/company`
- Write tools available: AI clients can persist guidelines after analyzing existing content
- Phase 3 (content tools) can import ContentGuidelinesRepository to fetch merged guidelines context when generating or editing content
- `doctrine:schema:update` will create sulu_mcp_content_guidelines and sulu_mcp_company_context tables when run in a Sulu project

---
*Phase: 02-context-discovery-guidelines*
*Completed: 2026-03-30*
