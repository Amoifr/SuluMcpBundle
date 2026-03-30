---
phase: quick
plan: 260330-ubq
subsystem: docs
tags: [agents, rules, conventions, ai-guidelines]
key-files:
  created:
    - AGENTS.md
    - rules/architecture.md
    - rules/coding.md
    - rules/commits.md
    - rules/testing.md
    - rules/tooling.md
    - rules/strict-mode.md
  modified:
    - CLAUDE.md
decisions:
  - AGENTS.md references all 6 rules files explicitly
  - GSD:agents-start block inserted before GSD:workflow-start in CLAUDE.md
  - No Docker/Symfony CLI patterns in any file (bundle, not app)
  - Tool classes mapped to Application layer in architecture.md
metrics:
  completed: "2026-03-30"
  tasks: 2
  files: 8
---

# Quick Task 260330-ubq: Adapt AGENTS.md, CLAUDE.md, and rules from sulu.ai

AGENTS.md and 6 rules files created for the sulu-mcp bundle, adapted from sulu.ai with app-specific patterns removed.

## What Was Done

### Task 1: Create AGENTS.md and rules/ directory (commit: 296ab64)
Created `AGENTS.md` at repo root with bundle-specific identity (library, not app), mandatory workflow using plain `composer` scripts, and an explicit index of all 6 rules files.

Created `rules/` directory with:
- `architecture.md` — maps Tool classes to Application layer, delegates to Sulu services, no separate Domain layer
- `coding.md` — declare(strict_types=1), Symfony standard, readonly services, correct composer scripts
- `commits.md` — short imperative summary, no AI attribution, no Co-Authored-By
- `testing.md` — unit for tool logic, functional/kernel for DI/integration, Prophecy, final test classes
- `tooling.md` — correct pre-PR workflow: fix, phpstan, lint, test
- `strict-mode.md` — minimal change, no unapproved dependencies, no AI references in commits

### Task 2: Update CLAUDE.md (commit: 98a433b)
Inserted `<!-- GSD:agents-start source:AGENTS.md -->` block before `GSD:workflow-start`. Block contains:
- Output discipline (minimal diffs, list modified files)
- Verification (state which composer scripts were run)
- Architecture discipline (delegate to Sulu services, follow rules/*)
- Clarification rule (ask before implementing rule violations)

All existing GSD-managed sections preserved intact.

## Adaptations from sulu.ai

| sulu.ai pattern | This bundle |
|-----------------|-------------|
| Docker + Symfony CLI serve | Removed (library, not app) |
| `symfony composer` / `symfony php` | Plain `composer` |
| `composer bootstrap-test-env` | Removed |
| `composer fix` (fix only) | `composer fix` (rector + php-cs-fixer) |
| DDD full layers (UI → App → Domain ← Infra) | Bundle layers: Tools (App) → Sulu services (external Domain) |
| App-specific patterns file | Not created |

## Deviations from Plan

None - plan executed exactly as written (PR creation task excluded per constraints).

## Self-Check: PASSED

- AGENTS.md: EXISTS at /Users/johannes/Development/ai/sulu-mcp/AGENTS.md
- rules/ directory: 6 files confirmed
- CLAUDE.md: GSD:agents-start block present at line 162
- Commits: 296ab64, 98a433b confirmed in git log
