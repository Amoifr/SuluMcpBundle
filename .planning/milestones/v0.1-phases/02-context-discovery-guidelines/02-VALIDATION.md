---
phase: 2
slug: context-discovery-guidelines
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-30
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10/11 |
| **Config file** | `phpunit.xml.dist` (project root) |
| **Quick run command** | `composer test -- --filter SuluMcp` |
| **Full suite command** | `composer test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `composer test -- --filter SuluMcp`
- **After every plan wave:** Run `composer test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 2-01-01 | 01 | 1 | RSRC-01 | unit | `composer test -- --filter TemplateResourceTest` | ❌ W0 | ⬜ pending |
| 2-01-02 | 01 | 1 | RSRC-02 | unit | `composer test -- --filter BlockTypeResourceTest` | ❌ W0 | ⬜ pending |
| 2-01-03 | 01 | 1 | RSRC-03 | unit | `composer test -- --filter WebspaceResourceTest` | ❌ W0 | ⬜ pending |
| 2-01-04 | 01 | 2 | RSRC-04 | unit | `composer test -- --filter SitemapResourceTest` | ❌ W0 | ⬜ pending |
| 2-02-01 | 02 | 1 | GUID-01 | unit | `composer test -- --filter ContentGuidelinesEntityTest` | ❌ W0 | ⬜ pending |
| 2-02-02 | 02 | 1 | GUID-02 | unit | `composer test -- --filter ContentGuidelinesResourceTest` | ❌ W0 | ⬜ pending |
| 2-02-03 | 02 | 2 | GUID-03 | unit | `composer test -- --filter CompanyContextEntityTest` | ❌ W0 | ⬜ pending |
| 2-02-04 | 02 | 2 | GUID-04 | unit | `composer test -- --filter CompanyContextResourceTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/Resource/TemplateResourceTest.php` — stubs for RSRC-01
- [ ] `tests/Unit/Resource/BlockTypeResourceTest.php` — stubs for RSRC-02
- [ ] `tests/Unit/Resource/WebspaceResourceTest.php` — stubs for RSRC-03
- [ ] `tests/Unit/Resource/SitemapResourceTest.php` — stubs for RSRC-04
- [ ] `tests/Unit/Entity/ContentGuidelinesEntityTest.php` — stubs for GUID-01
- [ ] `tests/Unit/Resource/ContentGuidelinesResourceTest.php` — stubs for GUID-02
- [ ] `tests/Unit/Entity/CompanyContextEntityTest.php` — stubs for GUID-03
- [ ] `tests/Unit/Resource/CompanyContextResourceTest.php` — stubs for GUID-04

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Template schema completeness matches actual Sulu admin UI | RSRC-01 | Requires live Sulu instance with templates configured | Read a page in admin, compare fields to MCP resource output |
| Navigation context name resolves correctly per webspace | RSRC-04 | Navigation contexts vary per project config | Check `sulu_website.navigation` config for actual context names |
| Guideline merging (global + webspace override) produces correct result | GUID-02 | Requires DB rows and override logic verification | Insert global row and webspace-specific row, read resource, verify merge |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
