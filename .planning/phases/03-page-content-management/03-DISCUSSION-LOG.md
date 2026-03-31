# Phase 3: Page Content Management - Discussion Log (Assumptions Mode)

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions captured in CONTEXT.md -- this log preserves the analysis.

**Date:** 2026-03-30
**Phase:** 03-page-content-management
**Mode:** assumptions
**Areas analyzed:** Content Read/Write, Block Operations, Tool Organization, Publish/Unpublish

## Assumptions Presented

### Content Read/Write via Sulu Message Bus
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Page CRUD via message bus (CreatePageMessage, ModifyPageMessage, RemovePageMessage) with EnableFlushStamp | Confident | STACK.md Sulu 3.0 hexagonal architecture, ARCHITECTURE.md anti-pattern #5 |
| Read via ContentResolver/ContentManager | Confident | STACK.md service layer documentation |

### Block Operations as Part of Page Modification
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Blocks manipulated via ModifyPageMessage (JSON content payload) | Likely | No separate block messages in STACK.md, BlocksResource.php confirms template-level concept |
| Separate MCP tools for block add/remove/reorder | Likely | ARCHITECTURE.md suggests separate tools, matches MCP best practice of focused tools |

### Tool Organization: Flat Directory
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| Keep flat src/Tool/ with naming prefix convention | Likely | All existing tools flat, but Phase 3 adds 8+ tools |

### Publish/Unpublish via Workflow Transition
| Assumption | Confidence | Evidence |
|------------|-----------|----------|
| ApplyWorkflowTransitionPageMessage with "publish"/"unpublish" transitions | Confident | STACK.md explicit documentation, REQUIREMENTS.md out-of-scope confirms no custom workflows |

## Corrections Made

### Page Tree Tool (User Addition)
- **Original assumption:** No page tree tool assumed (sitemap resource from Phase 2 was considered sufficient)
- **User correction:** Add a `sulu_page_tree` tool mirroring the Sulu admin page tree -- admin-style with UUID, title, URL, page type, has-children, parent UUID, depth, workflow state (draft/published), locale availability
- **Reason:** AI needs the full admin content tree to navigate site structure and pick parent pages when creating/moving content. The Phase 2 sitemap resource is depth-limited and navigation-focused, not sufficient for content management workflows.

## External Research Needed

- Exact `ContentResolver`/`ContentManager` API for reading pages (method signatures, query/filter capabilities)
- `CreatePageMessage`/`ModifyPageMessage` constructor signatures and content data structure
- BLCK-04 coverage confirmation (Phase 2 `sulu://blocks` resource sufficiency)
- Page tree implementation approach (NavigationRepositoryInterface vs ContentManager query)
