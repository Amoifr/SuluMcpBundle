---
phase: 04-extended-content-tools
plan: 02
subsystem: api
tags: [mcp, articles, blocks, publish, message-bus]

requires:
  - phase: 03-page-content-management
    provides: "Block tool patterns (add, remove, reorder), publish/unpublish tool patterns"
provides:
  - "3 article block MCP tools: add, remove, reorder"
  - "2 article workflow tools: publish, unpublish"
affects: []

key-files:
  created:
    - src/Tool/ArticleBlockAddTool.php
    - src/Tool/ArticleBlockRemoveTool.php
    - src/Tool/ArticleBlockReorderTool.php
    - src/Tool/ArticlePublishTool.php
    - src/Tool/ArticleUnpublishTool.php
    - tests/Unit/Tool/ArticleBlockAddToolTest.php
    - tests/Unit/Tool/ArticleBlockRemoveToolTest.php
    - tests/Unit/Tool/ArticleBlockReorderToolTest.php
    - tests/Unit/Tool/ArticlePublishToolTest.php
    - tests/Unit/Tool/ArticleUnpublishToolTest.php
  modified: []

key-decisions:
  - "Article block tools use ModifyArticleMessage (not ModifyPageMessage)"
  - "Article publish/unpublish use ApplyWorkflowTransitionArticleMessage"
  - "Same blockProperty parameter pattern as page block tools"

requirements-completed: [ARTC-04]

duration: 3min
completed: 2026-03-31
---

# Phase 04 Plan 02: Article Block & Publishing Tools Summary

**5 article tools completing the article content workflow: block add/remove/reorder + publish/unpublish**

## Accomplishments
- ArticleBlockAddTool, ArticleBlockRemoveTool, ArticleBlockReorderTool using read-modify-dispatch with ModifyArticleMessage
- ArticlePublishTool, ArticleUnpublishTool using ApplyWorkflowTransitionArticleMessage
- All tools follow established page block/publish patterns with article-specific message classes
- 9 unit tests passing

## Verification

- composer test: 216 tests, 629 assertions, OK
- composer phpstan: No errors
- composer lint: Clean

---
*Phase: 04-extended-content-tools*
*Completed: 2026-03-31*
