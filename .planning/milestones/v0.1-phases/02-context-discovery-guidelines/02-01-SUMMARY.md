---
phase: 02-context-discovery-guidelines
plan: 01
subsystem: api
tags: [mcp, sulu, php, resources, templates, blocks, webspaces, sitemap, doctrine]

requires:
  - phase: 01-bundle-foundation-transport
    provides: Bundle infrastructure, PingTool pattern, service wiring, DI extension with prepend()

provides:
  - sulu://templates McpResource — page templates with field schemas via FormMetadataProvider
  - sulu://blocks McpResource — deduplicated block types with available_in_templates
  - sulu://webspaces McpResource — webspace list with locales and prod-env URLs
  - sulu://sitemap/{webspace} McpResourceTemplate — depth-limited navigation tree per webspace
  - sitemap.max_depth bundle config node (default 3, range 1-10)
  - Doctrine ORM mapping registration in extension prepend() for SuluMcpServerBundle entity namespace

affects:
  - 02-02 — guidelines entity and resource build on this plan's Doctrine ORM prepend
  - 03-content-tools — MCP tools will use these resources as discovery layer

tech-stack:
  added: []
  patterns:
    - "McpResource attribute on service method with static sulu:// URI"
    - "McpResourceTemplate attribute on method with URI template variable matching param name"
    - "FormMetadataProvider injected as MetadataProviderInterface with explicit service alias in services.yaml"
    - "NavigationRepositoryInterface::getNavigationTree() with string-keyed properties map"
    - "Portal/Environment URL traversal pattern for webspace URLs (not Webspace::getUrls())"
    - "Navigation::getContextKeys() to find available navigation contexts"

key-files:
  created:
    - src/Resource/TemplatesResource.php
    - src/Resource/BlocksResource.php
    - src/Resource/WebspacesResource.php
    - src/Resource/SitemapResource.php
    - tests/Unit/Resource/TemplateResourceTest.php
    - tests/Unit/Resource/BlockTypeResourceTest.php
    - tests/Unit/Resource/WebspaceResourceTest.php
    - tests/Unit/Resource/SitemapResourceTest.php
  modified:
    - src/DependencyInjection/Configuration.php
    - src/DependencyInjection/SuluMcpServerExtension.php
    - config/services.yaml

key-decisions:
  - "WebspacesResource uses Portal::getEnvironment('prod')->getUrls()[0] for URL lookup (not non-existent Webspace::getUrls())"
  - "SitemapResource uses Webspace::getNavigation()->getContextKeys() not getNavigations() (the real Webspace API)"
  - "NavigationRepositoryInterface properties param uses string-keyed map (uuid => object.resource.id) not list"
  - "FormMetadataProvider autowired via explicit alias in services.yaml (@sulu_admin.metadata_provider.form) to avoid ambiguity"
  - "McpResourceTemplate (#[McpResourceTemplate]) used for sitemap — confirmed functional in mcp/sdk v0.4 per research"

patterns-established:
  - "Resource class: declare strict_types, namespace Sulu\\McpServerBundle\\Resource, constructor injection, #[McpResource] on method"
  - "Test scaffold: extends TestCase, createMock() for all dependencies, actual Sulu value objects when constructable"

requirements-completed:
  - RSRC-01
  - RSRC-02
  - RSRC-03
  - RSRC-04

duration: 13min
completed: "2026-03-30"
---

# Phase 2 Plan 1: Resource Discovery Layer Summary

**Four read-only MCP resources exposing Sulu CMS structure: templates with field schemas, block types with deduplication, webspaces with prod URLs, and per-webspace depth-limited sitemap via NavigationRepositoryInterface**

## Performance

- **Duration:** 13 min
- **Started:** 2026-03-30T14:25:56Z
- **Completed:** 2026-03-30T14:39:00Z
- **Tasks:** 2 (TDD: test scaffolds + implementation)
- **Files modified:** 10

## Accomplishments

- Four MCP resource classes covering full CMS discovery layer (templates, blocks, webspaces, sitemap)
- sitemap.max_depth bundle config with default 3, range 1-10
- Doctrine ORM mapping prepend for future entity registration (prep for plan 02-02)
- 16 new unit tests (33 total), all green; PHPStan clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Test scaffolds (TDD RED)** - `f0db9ac` (test)
2. **Task 2: Implementation + config** - `483f55f` (feat)

## Files Created/Modified

- `src/Resource/TemplatesResource.php` — sulu://templates McpResource, delegates to FormMetadataProvider
- `src/Resource/BlocksResource.php` — sulu://blocks McpResource, deduplicates block types across templates
- `src/Resource/WebspacesResource.php` — sulu://webspaces McpResource, traverses Portal/Environment for URLs
- `src/Resource/SitemapResource.php` — sulu://sitemap/{webspace} McpResourceTemplate, delegates to NavigationRepositoryInterface
- `src/DependencyInjection/Configuration.php` — added sitemap.max_depth config node
- `src/DependencyInjection/SuluMcpServerExtension.php` — added Doctrine ORM mapping prepend + sitemap parameter
- `config/services.yaml` — registered four resources; TemplatesResource/BlocksResource use explicit @sulu_admin.metadata_provider.form alias
- `tests/Unit/Resource/TemplateResourceTest.php` — 4 tests for RSRC-01
- `tests/Unit/Resource/BlockTypeResourceTest.php` — 4 tests for RSRC-02
- `tests/Unit/Resource/WebspaceResourceTest.php` — 3 tests for RSRC-03
- `tests/Unit/Resource/SitemapResourceTest.php` — 5 tests for RSRC-04

## Decisions Made

- Used `Portal::getEnvironment('prod')->getUrls()` rather than the non-existent `Webspace::getUrls()` — WebspacesResource traverses the Portal hierarchy
- Used `Webspace::getNavigation()->getContextKeys()` (not `getNavigations()`) — singular `Navigation` object on Webspace
- `NavigationRepositoryInterface::getNavigationTree()` `properties` arg is `array<string, string>` map, not a list
- Explicit service alias `@sulu_admin.metadata_provider.form` in services.yaml avoids autowiring ambiguity for MetadataProviderInterface

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] WebspacesResource URL lookup used non-existent Webspace::getUrls()**
- **Found during:** Task 2 (implementation)
- **Issue:** Plan's implementation called `$webspace->getUrls()` and `$url->getEnvironment()` — these methods do not exist on the real `Webspace` class. The Webspace API exposes portals which contain environments which contain URLs.
- **Fix:** Rewrote `getPrimaryUrl()` to iterate `$webspace->getPortals()`, try `$portal->getEnvironment('prod')`, then `$env->getUrls()[0]->getUrl()`, catching `EnvironmentNotFoundException` per Sulu's real exception hierarchy
- **Files modified:** src/Resource/WebspacesResource.php, tests/Unit/Resource/WebspaceResourceTest.php
- **Verification:** PHPStan clean, 3 webspace tests green
- **Committed in:** 483f55f (Task 2 commit)

**2. [Rule 1 - Bug] SitemapResource used non-existent Webspace::getNavigations()**
- **Found during:** Task 2 (implementation)
- **Issue:** Plan's implementation called `$ws->getNavigations()` treating it as a collection. Webspace has `getNavigation()` (singular) returning a `Navigation` object with `getContextKeys()` for the list.
- **Fix:** Changed to `$ws->getNavigation()` + `$navigation->getContextKeys()` with correct null handling per PHPStan
- **Files modified:** src/Resource/SitemapResource.php, tests/Unit/Resource/SitemapResourceTest.php
- **Verification:** PHPStan clean, 5 sitemap tests green
- **Committed in:** 483f55f (Task 2 commit)

**3. [Rule 1 - Bug] NavigationRepositoryInterface properties parameter type mismatch**
- **Found during:** Task 2 (PHPStan analysis)
- **Issue:** Plan passed `['uuid', 'title', 'url', 'depth']` (list) but interface requires `array<string, string>` (name => expression map per Sulu's SEAL-based implementation)
- **Fix:** Changed to `['uuid' => 'object.resource.id', 'title' => 'title', 'url' => 'url']` per Sulu's internal tests
- **Files modified:** src/Resource/SitemapResource.php
- **Verification:** PHPStan clean
- **Committed in:** 483f55f (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (all Rule 1 bugs — plan referenced non-existent Sulu 3.x API methods)
**Impact on plan:** All fixes necessary for runtime correctness. No scope creep. The fixes reflect gaps between plan research and actual Sulu 3.x API surface.

## Issues Encountered

- Worktree has no vendor directory — resolved by creating a minimal `vendor/autoload.php` wrapper that delegates to main repo vendor but overrides PSR-4 paths for the worktree's src/tests directories
- PHP-CS-Fixer reformatted constructor trailing commas and PHPDoc alignment — applied via `composer php-cs-fix`

## Next Phase Readiness

- Discovery layer complete: AI clients can now read `sulu://templates`, `sulu://blocks`, `sulu://webspaces`, `sulu://sitemap/{webspace}`
- Doctrine ORM mapping prepend registered — plan 02-02 can add ContentGuidelines and CompanyContext entities immediately
- All 33 tests green, PHPStan clean, no regressions

## Self-Check: PASSED

All created files exist. All commits verified (f0db9ac, 483f55f).

---
*Phase: 02-context-discovery-guidelines*
*Completed: 2026-03-30*
