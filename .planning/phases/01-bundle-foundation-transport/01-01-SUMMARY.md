---
phase: 01-bundle-foundation-transport
plan: 01
subsystem: infra
tags: [symfony-bundle, mcp, composer, phpunit, phpstan, php-cs-fixer, sulu-3]

# Dependency graph
requires: []
provides:
  - Symfony bundle skeleton (SuluMcpServerBundle) with DI extension and configuration
  - MCP Streamable HTTP endpoint configured at /_mcp via symfony/mcp-bundle route import
  - WebspaceLocaleValidator for reusable webspace/locale parameter validation
  - PingTool smoke-test MCP tool with #[McpTool] auto-discovery attribute
  - Sulu 3.0 test application with Composer path repository symlink
  - PHPUnit, PHPStan (level 6), PHP-CS-Fixer (@Symfony) dev tooling
affects: [01-02, 02-01, 02-02, 03-01]

# Tech tracking
tech-stack:
  added: [symfony/mcp-bundle ^0.6, mcp/sdk ^0.4, sulu/sulu ^3.0, phpunit ^10.5|^11.5, phpstan ^2.0, php-cs-fixer ^3.14]
  patterns: [symfony-bundle-skeleton, mcp-tool-attribute-discovery, webspace-locale-validation, tdd-with-mocked-sulu-services]

key-files:
  created:
    - composer.json
    - src/SuluMcpServerBundle.php
    - src/DependencyInjection/SuluMcpServerExtension.php
    - src/DependencyInjection/Configuration.php
    - config/services.yaml
    - config/routes.yaml
    - src/Validator/WebspaceLocaleValidator.php
    - src/Tool/PingTool.php
    - tests/Unit/Validator/WebspaceLocaleValidatorTest.php
    - tests/Unit/Tool/PingToolTest.php
    - phpunit.xml.dist
    - phpstan.neon
    - .php-cs-fixer.dist.php
    - test-app/composer.json
    - test-app/config/bundles.php
  modified: []

key-decisions:
  - "Bundle config root key: sulu_mcp_server with server_url (required) and mcp_path (default: /_mcp)"
  - "WebspaceLocaleValidator as a separate reusable service, injected into tools via constructor"
  - "PingTool validates then fetches webspace data (two-step: validate, then return info)"

patterns-established:
  - "MCP tool pattern: class with #[McpTool] attribute, constructor-injected dependencies, validates webspace/locale first"
  - "Webspace/locale validation: WebspaceLocaleValidator service used by all tools that accept webspace/locale params"
  - "Test pattern: PHPUnit TestCase with mocked WebspaceManagerInterface, Webspace, and Localization"
  - "Bundle structure: src/ for code, config/ for YAML services/routes, tests/Unit/ for tests"

requirements-completed: [TRNS-01, TRNS-02, TRNS-03, LOCL-01, LOCL-02]

# Metrics
duration: 3min
completed: 2026-03-29
---

# Phase 01 Plan 01: Bundle Foundation & Transport Summary

**Symfony bundle skeleton with MCP /_mcp endpoint, sulu_ping tool with webspace/locale validation, and Sulu 3.0 test app**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-29T19:54:48Z
- **Completed:** 2026-03-29T19:57:59Z
- **Tasks:** 2
- **Files modified:** 20

## Accomplishments
- Complete Symfony bundle skeleton (SuluMcpServerBundle) with DI extension loading services.yaml and Configuration defining sulu_mcp_server tree
- MCP Streamable HTTP endpoint configured at /_mcp via `type: mcp` route import from symfony/mcp-bundle
- sulu_ping tool auto-discovered via #[McpTool] attribute, validates webspace/locale parameters via WebspaceLocaleValidator
- Sulu 3.0 test application at test-app/ with Composer path repository symlinked to the bundle
- Unit tests for WebspaceLocaleValidator (4 tests) and PingTool (4 tests) with mocked Sulu services

## Task Commits

Each task was committed atomically:

1. **Task 1: Bundle skeleton, DI, config, test app, dev tooling** - `c363a62` (feat)
2. **Task 2 RED: Failing tests for validator and ping tool** - `30d3440` (test)
3. **Task 2 GREEN: WebspaceLocaleValidator and PingTool implementation** - `3871460` (feat)

## Files Created/Modified
- `composer.json` - Package definition for sulu/mcp-server-bundle with all dependencies
- `src/SuluMcpServerBundle.php` - Symfony bundle class extending AbstractBundle
- `src/DependencyInjection/SuluMcpServerExtension.php` - DI extension loading config/services.yaml
- `src/DependencyInjection/Configuration.php` - Configuration tree with server_url and mcp_path
- `config/services.yaml` - Autowired service definitions for validator and ping tool
- `config/routes.yaml` - MCP route import (type: mcp) for /_mcp endpoint
- `config/packages/sulu_mcp_server.yaml` - Default config using SULU_MCP_SERVER_URL env var
- `src/Validator/WebspaceLocaleValidator.php` - Reusable webspace/locale validation against WebspaceManager
- `src/Tool/PingTool.php` - Smoke-test MCP tool with #[McpTool(name: 'sulu_ping')]
- `tests/Unit/Validator/WebspaceLocaleValidatorTest.php` - 4 test cases for validator behavior
- `tests/Unit/Tool/PingToolTest.php` - 4 test cases including attribute reflection check
- `phpunit.xml.dist` - PHPUnit configuration with source coverage for src/
- `phpstan.neon` - PHPStan level 6 for src/
- `.php-cs-fixer.dist.php` - PHP-CS-Fixer with @Symfony ruleset
- `.gitignore` - Ignores vendor, var, cache files, composer.lock
- `LICENSE` - MIT license
- `test-app/composer.json` - Test app with path repository pointing to ../
- `test-app/config/bundles.php` - Registers FrameworkBundle and SuluMcpServerBundle
- `test-app/config/packages/framework.yaml` - Minimal framework config for testing
- `test-app/config/packages/sulu_mcp_server.yaml` - Test app bundle config with localhost URL
- `test-app/public/index.php` - Standard Symfony front controller
- `test-app/.gitignore` - Test app specific ignores

## Decisions Made
- Bundle config root key `sulu_mcp_server` with `server_url` (required, no default) and `mcp_path` (default: `/_mcp`) -- server_url is required because tools need it to construct absolute URLs
- WebspaceLocaleValidator extracted as a standalone service rather than inline in PingTool -- enables reuse across all future content tools
- PingTool performs validation first (via validator), then fetches webspace data separately -- clear separation of concerns

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None - all code paths are fully wired with real implementations (validator uses WebspaceManagerInterface, PingTool uses validator and WebspaceManager).

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Bundle skeleton complete, ready for OAuth 2.0 authorization server (Plan 01-02)
- WebspaceLocaleValidator pattern established for all future content tools
- Test infrastructure (PHPUnit, mocking patterns) ready for additional test coverage
- Test application available for integration testing once composer install is possible

## Self-Check: PASSED

All 22 created files verified present. All 3 task commits verified in git log.

---
*Phase: 01-bundle-foundation-transport*
*Completed: 2026-03-29*
