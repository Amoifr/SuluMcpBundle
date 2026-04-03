---
phase: 3
slug: page-content-management
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-30
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 or ^11.5 |
| **Config file** | `phpunit.xml.dist` |
| **Quick run command** | `composer test` |
| **Full suite command** | `composer test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `composer test`
- **After every plan wave:** Run `composer test && composer phpstan`
- **Before `/gsd:verify-work`:** Full suite must be green (`composer fix && composer lint && composer phpstan && composer test`)
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | PAGE-01 | unit | `composer test -- --filter PageGetToolTest` | ❌ W0 | ⬜ pending |
| 03-01-02 | 01 | 1 | PAGE-02 | unit | `composer test -- --filter PageListToolTest` | ❌ W0 | ⬜ pending |
| 03-01-03 | 01 | 1 | PAGE-03 | unit | `composer test -- --filter PageCreateToolTest` | ❌ W0 | ⬜ pending |
| 03-01-04 | 01 | 1 | PAGE-04 | unit | `composer test -- --filter PageUpdateToolTest` | ❌ W0 | ⬜ pending |
| 03-01-05 | 01 | 1 | PAGE-05 | unit | `composer test -- --filter PageDeleteToolTest` | ❌ W0 | ⬜ pending |
| 03-01-06 | 01 | 1 | PAGE-01,02 | unit | `composer test -- --filter PageTreeToolTest` | ❌ W0 | ⬜ pending |
| 03-02-01 | 02 | 2 | BLCK-01 | unit | `composer test -- --filter BlockAddToolTest` | ❌ W0 | ⬜ pending |
| 03-02-02 | 02 | 2 | BLCK-02 | unit | `composer test -- --filter BlockRemoveToolTest` | ❌ W0 | ⬜ pending |
| 03-02-03 | 02 | 2 | BLCK-03 | unit | `composer test -- --filter BlockReorderToolTest` | ❌ W0 | ⬜ pending |
| 03-02-04 | 02 | 2 | PUBL-01 | unit | `composer test -- --filter PagePublishToolTest` | ❌ W0 | ⬜ pending |
| 03-02-05 | 02 | 2 | PUBL-02 | unit | `composer test -- --filter PageUnpublishToolTest` | ❌ W0 | ⬜ pending |
| 03-02-06 | 02 | 2 | D-13 | unit | `composer test -- --filter GuidelineGenerationPromptTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/Tool/PageGetToolTest.php` — stubs for PAGE-01
- [ ] `tests/Unit/Tool/PageListToolTest.php` — stubs for PAGE-02
- [ ] `tests/Unit/Tool/PageCreateToolTest.php` — stubs for PAGE-03
- [ ] `tests/Unit/Tool/PageUpdateToolTest.php` — stubs for PAGE-04
- [ ] `tests/Unit/Tool/PageDeleteToolTest.php` — stubs for PAGE-05
- [ ] `tests/Unit/Tool/PageTreeToolTest.php` — stubs for page tree
- [ ] `tests/Unit/Tool/BlockAddToolTest.php` — stubs for BLCK-01
- [ ] `tests/Unit/Tool/BlockRemoveToolTest.php` — stubs for BLCK-02
- [ ] `tests/Unit/Tool/BlockReorderToolTest.php` — stubs for BLCK-03
- [ ] `tests/Unit/Tool/PagePublishToolTest.php` — stubs for PUBL-01
- [ ] `tests/Unit/Tool/PageUnpublishToolTest.php` — stubs for PUBL-02
- [ ] `tests/Functional/Tool/Page*Test.php` — functional tests for page CRUD integration
- [ ] `tests/Functional/Tool/Block*Test.php` — functional tests for block management integration

*Existing infrastructure (PHPUnit, phpunit.xml.dist, composer test script) covers framework needs.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| AI client creates page end-to-end via MCP | All PAGE-* | Requires running Sulu instance with MCP transport | Start Sulu dev server, connect MCP client, execute page create/read/update/delete/publish cycle |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
