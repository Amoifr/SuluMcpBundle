# Phase 2: Context Discovery & Guidelines - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions captured in CONTEXT.md — this log preserves the discussion.

**Date:** 2026-03-30
**Phase:** 02-context-discovery-guidelines
**Mode:** discuss
**Areas discussed:** Guidelines data model, Storage backend, Sitemap scope, Resource URI scheme

## Areas Discussed

### Guidelines Data Model
| Question | Answer |
|----------|--------|
| How should guidelines be structured? | Free-text fields per topic (tone, audience, style, brand_rules, dos, don'ts) |
| AI-generated guidelines idea | User wants AI to generate guidelines by analyzing content — via MCP Prompt + write tool |
| MCP Prompt in Phase 2 or 3? | Deferred to Phase 3 (depends on page/article reading tools) |
| Write tool for AI to save guidelines? | Yes — sulu_update_guidelines included in Phase 2 |

### Storage Backend
| Question | Answer |
|----------|--------|
| Entity schema | Free-text fields per topic (not JSON column) |
| Company context source | Separate Doctrine entity (company_name, description, industry, website, key_products) |

### Sitemap Scope
| Question | Answer |
|----------|--------|
| Fields per page | Minimal: UUID, URL, title, depth |
| Pagination | Depth-limited, configurable, default 3 levels |

### Resource URI Scheme
| Question | Answer |
|----------|--------|
| URI prefix | Consistent sulu:// for all resources |
| Templates/blocks granularity | Global with webspace filter (not webspace-scoped URIs) |

## Corrections / Scope Flags

- **Scope flag:** User's initial idea (AI generates guidelines) would require page reading tools (Phase 3). Resolved by splitting: Phase 2 delivers write tool infrastructure, Phase 3 delivers the MCP Prompt that orchestrates generation.
- **Discarded idea:** "Use context stuff to store context" — user withdrew this idea.

## No corrections to prior Phase 1 decisions.
