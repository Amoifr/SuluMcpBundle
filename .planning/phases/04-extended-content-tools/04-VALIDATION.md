---
phase: 04
slug: extended-content-tools
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-31
---

# Phase 04 — Validation Strategy

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
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | ARTC-01, ARTC-02 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-01-02 | 01 | 1 | ARTC-03, ARTC-04, ARTC-05 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-01-03 | 01 | 1 | PUBL-01, PUBL-02 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-02-01 | 02 | 2 | TAXO-01, TAXO-02, TAXO-03 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-02-02 | 02 | 2 | TAXO-04, TAXO-05, TAXO-06 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-02-03 | 02 | 2 | MDIA-01, MDIA-02, MDIA-03 | unit | `composer test` | ❌ W0 | ⬜ pending |
| 04-02-04 | 02 | 2 | READ-01, READ-02, READ-03 | unit | `composer test` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/Tool/ArticleGetToolTest.php` — stubs for ARTC-01, ARTC-02
- [ ] `tests/Unit/Tool/ArticleCreateToolTest.php` — stubs for ARTC-03, ARTC-04, ARTC-05
- [ ] `tests/Unit/Tool/TagCreateToolTest.php` — stubs for TAXO-01, TAXO-02, TAXO-03
- [ ] `tests/Unit/Tool/CategoryCreateToolTest.php` — stubs for TAXO-04, TAXO-05, TAXO-06
- [ ] `tests/Unit/Tool/MediaListToolTest.php` — stubs for MDIA-01, MDIA-02, MDIA-03
- [ ] `tests/Unit/Tool/SnippetGetToolTest.php` — stubs for READ-01, READ-02, READ-03

*Existing test infrastructure from Phase 1-3 covers framework setup.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| MCP client can call article tools | ARTC-01-05 | Requires live MCP connection | Connect Claude.ai, create/read/update/delete article |
| Category tree structure display | TAXO-05 | Visual hierarchy verification | Call sulu_category_list, verify nested parent-child |
| Media format URLs resolve | MDIA-02 | Requires running web server | Call sulu_media_get, open format URLs in browser |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
