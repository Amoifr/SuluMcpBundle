# Phase 4: Extended Content Tools - Discussion Log (Assumptions Mode)

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions captured in CONTEXT.md -- this log preserves the analysis.

**Date:** 2026-03-31
**Phase:** 04-extended-content-tools
**Mode:** assumptions
**Areas analyzed:** Article CRUD Approach, Block Tools for Articles, Taxonomy Tools, Media Tools, Read-Only Entity Tools, Plan Split

## Assumptions Presented

### Article CRUD Approach
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Mirror page tool pattern with Article message classes | Confident | src/Tool/Page*.php, CLAUDE.md Sulu 3.0 Service Layer |
| Articles are flat (no tree) | Confident | No MoveArticleMessage/OrderArticleMessage in CLAUDE.md |
| Article create takes `type` parameter | Confident | ARTC-03 requirement |
| Article publish uses ApplyWorkflowTransitionArticleMessage | Confident | Parallel to page pattern |
| Article webspace semantics TBD | Unclear | CreateArticleMessage constructor unknown |

### Block Tools for Articles
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Separate article block tools (not generic) | Likely | src/Tool/Block*.php tightly coupled to Page, flat directory convention |
| Use ModifyArticleMessage internally | Confident | Parallel to page block pattern |
| Same blockProperty parameter pattern | Confident | Phase 3 D-08 |

### Taxonomy & Media Service Layer
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Tags/categories/media use manager interfaces, not message bus | Confident | CLAUDE.md Traditional Bundles section |
| Category list returns tree structure | Likely | TAXO-05 requirement, exact API needs research |
| Media tools: list, get, update metadata only | Confident | MDIA-01/02/03 requirements |

### Read-Only Entity Tools
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Snippets use ContentManager (hexagonal packages) | Likely | CLAUDE.md shows snippets in packages/ |
| Navigation uses WebsiteBundle service | Likely | CLAUDE.md WebsiteBundle section |
| Contacts/accounts conditional on bundle presence | Likely | ContactBundle may be optional |

### Plan Split
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| 04-01 articles, 04-02 taxonomy/media/read-only | Confident | ROADMAP.md, grouped by service pattern |

## Corrections Made

No corrections -- all assumptions confirmed (auto mode).

## Auto-Resolved

- Article webspace semantics (Unclear): auto-selected "researcher will investigate CreateArticleMessage constructor; tools accept optional webspace parameter"
