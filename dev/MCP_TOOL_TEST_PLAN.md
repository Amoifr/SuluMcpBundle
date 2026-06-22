# Sulu MCP — End-to-End Tool Test Plan

Run this against the dev fixture (the `dev/` Symfony app, with `PageFixtures` /
`ArticleFixtures` loaded). Designed for a fresh Claude Code session: paste this
file (or run it section by section) to re-exercise every tool and confirm that
the bugs called out in **§ Known Issues** have been fixed.

The plan creates one test page + one test article, walks every tool end-to-end
(including nested blocks via two different paths), then deletes everything.
Pre-existing fixture data must remain untouched.

## Prerequisites

- Sulu MCP server running with the dev `.mcp.json` mounted (Claude Code session
  has `mcp__sulu__*` tools available).
- Authenticated as `admin` against webspace `website` (locale `en`).
- Fixtures loaded: at minimum the homepage `019e08dd-e9a5-708d-8dd5-b1a062ca4bbc`
  and `Blog` page `019e08dd-ea9c-78e0-bc1f-e85658b20ee0` should exist (UUIDs
  taken from `PageFixtures`). If the IDs differ in your install, capture them
  via `sulu_page_tree` in step 1.2 and substitute below.

## Quickstart prompt for a new session

> Run the end-to-end MCP tool test in `dev/MCP_TOOL_TEST_PLAN.md`. Execute every
> step in order, using the dev fixture. Stop and report at any deviation from
> the **expected** results in § Known Issues — those are the bugs we're checking
> are fixed. Clean up at the end.

---

## Phase 1 — Discovery (read-only)

1.1 `sulu_ping` → expect `status: ok`, `user: admin`, webspace `website`.

1.2 `sulu_get_context` `webspace=website` → expect templates `homepage` +
   `default`, block types including `section` (default-only) with a nested
   `blocks` field. Capture the homepage UUID from § Prerequisites or
   `sulu_page_tree`.

1.3 In parallel:
   - `sulu_page_tree` `webspace=website locale=en`
   - `sulu_page_list` `webspace=website locale=en`
   - `sulu_article_list` `locale=en`
   - `sulu_snippet_list` `locale=en`
   - `sulu_category_list` `locale=en`
   - `sulu_tag_list`
   - `sulu_media_list` `locale=en limit=5`
   - `sulu_contact_list` `type=contact limit=5`
   - `sulu_contact_list` `type=account limit=5`

   Confirm at least one page and >0 articles exist; record any media id /
   snippet uuid you find for steps 4.4 / 4.5.

---

## Phase 2 — Page CRUD + nested blocks

Variables: `PAGE_UUID` is set by 2.1. `HOMEPAGE_UUID` from § Prerequisites.

2.1 `sulu_page_create`
   - `webspace=website locale=en template=default`
   - `title="MCP Tool Test Page"`
   - `parentId=<HOMEPAGE_UUID>`
   - `content={"article":"<p>This page exercises every Sulu MCP tool, including nested blocks.</p>"}`
   - **Expect** `success:true`. Save the returned `uuid` as `PAGE_UUID`.

2.2 Add three top-level blocks (one call each):
   - `sulu_block_add` blockType=`heading` blockProperty=`blocks`
     blockData=`[{"title":"Welcome to the MCP test page"}]`
   - `sulu_block_add` blockType=`text`
     blockData=`[{"content":"<p>This is a top-level <strong>text block</strong> added via MCP.</p>"}]`
   - `sulu_block_add` blockType=`quote`
     blockData=`[{"text":"<p>The best way to predict the future is to invent it.</p>","attribution":"Alan Kay"}]`
   - **Expect** `blockCount` 1, 2, 3 respectively. **All three must succeed in
     one call each.** (See Known Issue #1 — multi-field blocks like quote
     historically failed unless wrapped this way.)

2.3 `sulu_block_list` `type=page uuid=<PAGE_UUID> blockProperty=blocks` →
   expect three blocks back with **clean** keys: `{type, title}` /
   `{type, content}` / `{type, text, attribution}`. **No integer keys
   ("0", "1") anywhere in the response.**

2.4 Add a section block with **inline nested children** (one call):
   ```
   sulu_block_add blockType=section blockProperty=blocks
     blockData=[{"title":"Nested section via inline blockData","blocks":[
       {"type":"heading","title":"Inside the section"},
       {"type":"text","content":"<p>This text block is <em>nested</em> inside a section block.</p>"},
       {"type":"quote","text":"<p>Nested deeply but not lost.</p>","attribution":"MCP Test"}
     ]}]
   ```
   **Expect** `blockCount:4`. Confirm via `sulu_page_get` that the section
   carries a `blocks` array with three children.

2.5 Add a heading block with an **explicit `_id`** to test update + nested-add
   workflows:
   - `sulu_block_add` blockType=`heading`
     blockData=`[{"_id":"test-id-1","title":"Block with explicit _id"}]`
   - **Expect** `blockCount:5`.

2.6 `sulu_block_update` — should work whether or not the id'd block is first.
   **After fix this should work without reorder; today reorder is a workaround
   (see Known Issue #2).**
   - First try directly: `sulu_block_update` `type=page uuid=<PAGE_UUID>`
     `blockId=test-id-1`
     `blockData=[{"title":"Updated heading via block_update"}]`
   - If that fails with "Block with _id ... not found", run a one-time
     `sulu_block_reorder` `newOrder=[4,0,1,2,3]` and retry the update. Note
     this in the test report.
   - **Expected after fix**: update succeeds without reorder.

2.7 Add a section with explicit `_id`, then nest into it via `parentBlockId`:
   - `sulu_block_add` blockType=`section`
     blockData=`[{"_id":"section-id-1","title":"Section for parentBlockId nesting"}]`
   - `sulu_block_add` blockType=`heading` parentBlockId=`section-id-1`
     blockData=`[{"_id":"child-id-1","title":"Nested via parentBlockId"}]`
   - `sulu_block_add` blockType=`text` parentBlockId=`section-id-1`
     blockData=`[{"_id":"child-id-2","content":"<p>This nested text was added <strong>after</strong> the parent existed.</p>"}]`
   - `sulu_block_list` `type=page uuid=<PAGE_UUID> blockProperty=blocks
     parentBlockId=section-id-1` → **expect 2 children with `_id` set**.

2.8 Deep update of a nested block:
   - `sulu_block_update` `type=page uuid=<PAGE_UUID> blockId=child-id-1`
     `blockData=[{"title":"Nested heading — updated through deep traversal"}]`
   - **Expect** `blockPath:[<sectionIndex>,0]` (5 or 0 depending on whether
     2.6 reordered).

2.9 Reorder + remove top-level blocks:
   - `sulu_block_reorder` `newOrder=[3,0,1,2,...]` (any valid permutation of
     current indices) → expect `success:true`.
   - `sulu_block_remove` removing one of the simple blocks by index → expect
     `blockCount` decreases by one.
   - **Known Issue #1 corollary**: removing a corrupted (integer-keyed) block
     used to throw the same `str_contains` error. After fix, remove must
     succeed even on legacy bad data.

2.10 `sulu_page_update`:
   - `uuid=<PAGE_UUID> locale=en title="MCP Tool Test Page (updated)"`
     `content={"article":"<p>Edited via sulu_page_update.</p>"}`
   - **Expect** `success:true` and the new title in the returned data.
   - **Also confirm**: `sulu_page_get` on `<PAGE_UUID>` no longer surfaces `seo`,
     `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap`, or `excerpt` in `data` —
     those are now reserved for the dedicated tools (steps 2.13 / 2.14).

2.11 Preview links (pages):
   - `sulu_preview_link_generate` `resourceKey=pages uuid=<PAGE_UUID>
     locale=en webspace=website` → expect `preview_url` + `token`.
   - `sulu_preview_link_revoke` `resourceKey=pages uuid=<PAGE_UUID>
     locale=en` → expect `action:revoked`.

2.12 Publish / unpublish:
   - `sulu_page_publish` `uuid=<PAGE_UUID> locale=en` → expect
     `action:published`. (Auto-mode classifier may ask the user; that's
     fine.)
   - `sulu_page_unpublish` `uuid=<PAGE_UUID> locale=en` → expect
     `action:unpublished`.

2.13 Page SEO:
   - `sulu_page_seo_get` `uuid=<PAGE_UUID> locale=en` → expect a response with
     `seo`, `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` keys (values empty
     / false on a freshly-created page).
   - `sulu_page_seo_update` `uuid=<PAGE_UUID> locale=en title="MCP SEO Title"
     description="MCP SEO description" keywords="mcp,test"` → `success:true`
     with the updated `seo` object echoed back.
   - `sulu_page_seo_update` `uuid=<PAGE_UUID> locale=en noIndex=true` →
     `success:true`. Re-read with `sulu_page_seo_get` and confirm
     **partial-update** semantics: `seo.title` from the previous call is still
     `"MCP SEO Title"`, and `seoNoIndex` is now `true`.

2.14 Page excerpt:
   - `sulu_page_excerpt_get` `uuid=<PAGE_UUID> locale=en` → expect an `excerpt`
     object (likely empty on a fresh page).
   - `sulu_page_excerpt_update` `uuid=<PAGE_UUID> locale=en
     title="MCP Excerpt Title" description="A short teaser." more="Read more →"`
     → `success:true` with the merged `excerpt` echoed back.
   - If a media id was captured in step 1.3:
     `sulu_page_excerpt_update` `uuid=<PAGE_UUID> locale=en imageId=<MEDIA_ID>`
     → re-read with `sulu_page_excerpt_get` and confirm `excerpt.image.id`
     equals `<MEDIA_ID>` AND the previous `excerpt.title` survived.

(Page is **not** deleted yet — Phase 5 cleans up.)

---

## Phase 3 — Article CRUD + nested blocks

Variables: `ARTICLE_UUID` set by 3.1. `BLOG_PAGE_UUID` from § Prerequisites.

3.1 `sulu_article_create`
   - `locale=en template=article title="MCP Tool Test Article"`
   - `content={"article":"<p>Article created via MCP for tool testing.</p>",`
     `"url":{"page":{"path":"/blog","uuid":"<BLOG_PAGE_UUID>"},"suffix":"/mcp-tool-test-article"}}`
   - Save returned `uuid` as `ARTICLE_UUID`.

3.2 In parallel, three article block adds:
   - `sulu_block_add` `type=article uuid=<ARTICLE_UUID>` blockType=`heading`
     blockData=`[{"_id":"art-h1","title":"Article heading"}]`
   - `sulu_block_add` `type=article uuid=<ARTICLE_UUID>` blockType=`text`
     blockData=`[{"_id":"art-t1","content":"<p>First paragraph.</p>"}]`
   - `sulu_block_add` `type=article uuid=<ARTICLE_UUID>` blockType=`section`
     blockData=`[{"_id":"art-sec","title":"Article section with inline nested blocks","blocks":[`
       `{"_id":"art-sec-h","type":"heading","title":"Inside section"},`
       `{"_id":"art-sec-t","type":"text","content":"<p>Inline-nested text.</p>"}`
     `]}]`

   Order in the resulting article doesn't matter as long as `blockCount=3`.

3.3 Nest via `parentBlockId`:
   - `sulu_block_add` `type=article uuid=<ARTICLE_UUID>` blockType=`quote` parentBlockId=`art-sec`
     blockData=`[{"_id":"art-sec-q","text":"<p>A quote nested through parentBlockId.</p>","attribution":"Test"}]`

3.4 `sulu_article_get` `uuid=<ARTICLE_UUID> locale=en` → expect a `blocks`
   array of summaries (`{index,_id,type,title}`). The section summary must
   contain a nested `blocks` array of three children including
   `art-sec-q`. **Every top-level and nested block has `_id` populated.**
   **Also confirm**: the response does NOT include `seo`, `seoNoIndex`,
   `seoNoFollow`, `seoHideInSitemap`, or `excerpt` — those are exposed only
   through the dedicated tools (steps 3.8 / 3.9).

3.5 Block ops on article:
   - `sulu_block_update` `type=article uuid=<ARTICLE_UUID> blockId=art-sec-h
     blockData=[{"title":"Updated nested heading"}]` → `blockPath:[2,0]`
     (or whatever the section's current index is).
   - `sulu_block_reorder` `type=article uuid=<ARTICLE_UUID> blockProperty=blocks newOrder=[2,0,1]`.
   - `sulu_article_update` `uuid=<ARTICLE_UUID> title="MCP Tool Test Article (updated)"`.
   - `sulu_block_remove` `type=article uuid=<ARTICLE_UUID> blockProperty=blocks blockIndex=2` →
     `blockCount:2`.

3.6 Preview links (articles):
   - `sulu_preview_link_generate` `resourceKey=articles uuid=<ARTICLE_UUID> locale=en`
   - `sulu_preview_link_revoke` `resourceKey=articles uuid=<ARTICLE_UUID> locale=en`

3.7 Publish / unpublish:
   - `sulu_article_publish` `uuid=<ARTICLE_UUID> locale=en`
   - `sulu_article_unpublish` `uuid=<ARTICLE_UUID> locale=en`

3.8 Article SEO:
   - `sulu_article_seo_get` `uuid=<ARTICLE_UUID> locale=en` → expect `seo`,
     `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` keys.
   - `sulu_article_seo_update` `uuid=<ARTICLE_UUID> locale=en
     title="Article SEO Title" canonicalUrl="https://example.com/canonical"`
     → `success:true`.
   - `sulu_article_seo_update` `uuid=<ARTICLE_UUID> locale=en hideInSitemap=true`
     → re-read with `sulu_article_seo_get` and confirm `seo.title` still
     `"Article SEO Title"` and `seoHideInSitemap` is now `true` (partial update).

3.9 Article excerpt:
   - `sulu_article_excerpt_get` `uuid=<ARTICLE_UUID> locale=en` → expect an
     `excerpt` object.
   - `sulu_article_excerpt_update` `uuid=<ARTICLE_UUID> locale=en
     title="Article Excerpt" description="Article teaser copy."` →
     `success:true` with merged excerpt echoed back.
   - Re-read with `sulu_article_excerpt_get` and confirm both fields persisted.

3.10 One-shot authoring (article create with full payload):
   - `sulu_article_create`
     - `locale=en template=article title="MCP One-Shot Article"`
     - `content={"article":"<p>Created in a single call with blocks, excerpt, and SEO.</p>","blocks":[{"_id":"os-sec","type":"section","title":"One-shot section","blocks":[{"_id":"os-sec-h","type":"heading","title":"Inside one-shot section"},{"_id":"os-sec-t","type":"text","content":"<p>Nested inside the one-shot section.</p>"}]}]}`
     - `excerpt={"title":"One-Shot Excerpt","description":"Teaser set at create time."}`
     - `seo={"title":"One-Shot SEO Title","noIndex":true}`
   - **Expect**: `success:true`. Save the returned `uuid` as `ARTICLE2_UUID`.
   - **Assert**: every block in the create response has `_id` populated (no
     empty `_id` fields anywhere in the `blocks` tree).
   - Follow-up `sulu_article_seo_get` `uuid=<ARTICLE2_UUID> locale=en` →
     confirm `seo.title="One-Shot SEO Title"` and `seoNoIndex=true`.
   - Follow-up `sulu_article_excerpt_get` `uuid=<ARTICLE2_UUID> locale=en` →
     confirm `excerpt.title="One-Shot Excerpt"` and
     `excerpt.description="Teaser set at create time."`.

---

## Phase 4 — Taxonomy, search, media, snippet

4.1 Tags:
   - `sulu_tag_create` `name=mcp-test-tag` → save returned `id` as `TAG_ID`.
   - `sulu_tag_list` → confirm the new tag is present.

4.2 Categories:
   - `sulu_category_create` `locale=en name="MCP Test Category"
     key=mcp-test-cat` → save id as `CAT_ID`.
   - `sulu_category_create` `locale=en name="MCP Test Subcategory"
     parentId=<CAT_ID>` → save id as `SUBCAT_ID`.
   - `sulu_category_list` `locale=en` → confirm tree shape with parent + child.

4.3 Search:
   - `sulu_content_search` `query="Sulu MCP" locale=en webspace=website
     type=pages limit=5` → results > 0, all `resourceKey:pages`.
   - `sulu_content_search` `query="Aphex" locale=en type=articles limit=3` →
     at least one article result.

4.4 Media (only if 1.3 found media):
   - `sulu_media_get` `id=<existing-id> locale=en` → returns metadata + format
     URLs.
   - `sulu_media_update` `id=<existing-id> locale=en
     title="MCP test (revert me)"` → `success:true`. Then revert by passing
     the original title.

4.5 Snippet:
   - `sulu_snippet_create` `locale=en template=<a snippet template key>
     title="MCP Test Snippet" content={...}` → `success:true`. Save the
     returned `uuid` as `SNIPPET_UUID`.
   - `sulu_snippet_update` `uuid=<SNIPPET_UUID> locale=en
     title="MCP Test Snippet (updated)"` → `success:true` with updated title
     echoed back.
   - If 1.3 found an existing snippet: `sulu_snippet_get`
     `uuid=<existing-uuid> locale=en` → expect content data.
   - `sulu_snippet_get` `uuid=<SNIPPET_UUID> locale=en` → confirm
     `title="MCP Test Snippet (updated)"`.
   - **Note**: there is no `sulu_snippet_delete` tool. The test snippet
     (`SNIPPET_UUID`) must be removed manually via the Sulu admin UI — it
     cannot be cleaned up via MCP.

---

## Phase 5 — Cleanup

Delete everything created in this run, in this order. Each delete must succeed.

5.1 `sulu_category_delete` `id=<SUBCAT_ID>`
5.2 `sulu_category_delete` `id=<CAT_ID>`
5.3 `sulu_tag_delete` `id=<TAG_ID>`
5.4 `sulu_article_delete` `uuid=<ARTICLE_UUID> locale=en`
5.5 `sulu_article_delete` `uuid=<ARTICLE2_UUID> locale=en`
5.6 `sulu_page_delete` `uuid=<PAGE_UUID> locale=en`

Final sanity check: re-run `sulu_page_list` / `sulu_article_list` /
`sulu_tag_list` / `sulu_category_list` and confirm the test items are gone
**and** the original fixture content (homepage, About Us, Our Services, Blog,
Music Artists, Contact, the 99 articles) is intact.

---

## Known Issues being verified by this plan

The plan was written after a 2026-05-08 end-to-end run that surfaced these.
Both should be **fixed** for the plan to pass cleanly.

**Issue #1 — `blockData` shape is fragile.**

✅ **RESOLVED.** The `blockData` parameter is now declared as a flat `object` schema and validated up front by `BlockDataValidator`, which rejects unknown keys and the `{name,value}` storage shape before they can corrupt Sulu's content data.

- ~~Schema declares `blockData: array`, but the trait
  `BlockDataNormalizerTrait::normalizeBlockData` only flattens lists with
  exactly one element. A list of `{name,value}` pairs (e.g.
  `[{"name":"text","value":"..."},{"name":"attribution","value":"..."}]`) is
  stored verbatim, producing integer-keyed sub-arrays in Sulu's content data.~~
- ~~Subsequent reads then crash with
  `str_contains(): Argument #1 ($haystack) must be of type string, int given`
  inside Sulu's `MetadataResolver`.~~
- ~~Worse: `sulu_block_remove` *also* throws on the corrupted indices, so the
  only recovery is `sulu_page_delete` + recreate.~~
- **Expected after fix**: any reasonable shape AI clients send (single-object
  list, raw object, list of `{name,value}`) is normalized into the right
  associative form. Step 2.2's quote block (multi-field) must succeed in one
  call, and Phase 5 cleanup must succeed even if intermediate state was
  partially malformed.

**Issue #2 — `_id` is never auto-generated.**

- `BlockAddTool` and `ArticleBlockAddTool` don't emit `_id`, and neither do
  `PageFixtures` / `ArticleFixtures`. Stored blocks come back without one.
- `ContentNormalizerTrait::detectBlockProperties()` only recognizes a
  property as containing blocks when the **first** item has both `_id` and
  `type`. With a mix of id'd and non-id'd top-level blocks,
  `sulu_page_get` returns full content instead of summaries, and
  `findBlockPath` returns null — so `sulu_block_update` and `parentBlockId`
  nested-add fail with "Block with _id ... not found" even though the block
  is right there.
- Today's workaround (used in this plan): pass `_id` explicitly in every
  `blockData`, and ensure the *first* top-level block has one.
- **Expected after fix**: `_id` is auto-generated on add (and survives
  reads), so step 2.5 / 2.7 / 3.2 don't need to pass `_id` manually, and
  step 2.6 (`block_update`) works regardless of where the target block sits
  in the array.

**Issue #3 — Auto-mode classifier may halt destructive ops.**

Not a Sulu bug, but worth noting: `sulu_page_publish`,
`sulu_*_delete`, and `sulu_tag_delete` / `sulu_category_delete` may be
denied by Claude Code's auto-mode classifier on the first call. Re-issuing
the same call in the next turn typically passes once the classifier sees
prior context. This is expected behavior, not a tool defect.

---

## Coverage matrix (46 tools)

| Tool | Phase |
|------|-------|
| `sulu_ping` | 1.1 |
| `sulu_get_context` | 1.2 |
| `sulu_page_tree` | 1.3 |
| `sulu_page_list` | 1.3, 5 |
| `sulu_page_create` | 2.1 |
| `sulu_page_get` | 2.4, 2.5, 2.10 |
| `sulu_page_update` | 2.10 |
| `sulu_page_publish` | 2.12 |
| `sulu_page_unpublish` | 2.12 |
| `sulu_page_seo_get` | 2.13 |
| `sulu_page_seo_update` | 2.13 |
| `sulu_page_excerpt_get` | 2.14 |
| `sulu_page_excerpt_update` | 2.14 |
| `sulu_page_delete` | 5.6 |
| `sulu_block_add` | 2.2, 2.4, 2.5, 2.7, 3.2, 3.3 |
| `sulu_block_list` | 2.3, 2.7 |
| `sulu_block_update` | 2.6, 2.8, 3.5 |
| `sulu_block_remove` | 2.9, 3.5 |
| `sulu_block_reorder` | 2.6 (workaround), 2.9, 3.5 |
| `sulu_article_list` | 1.3, 5 |
| `sulu_article_create` | 3.1 |
| `sulu_article_get` | 3.4 |
| `sulu_article_update` | 3.5 |
| `sulu_article_publish` | 3.7 |
| `sulu_article_unpublish` | 3.7 |
| `sulu_article_seo_get` | 3.8 |
| `sulu_article_seo_update` | 3.8 |
| `sulu_article_excerpt_get` | 3.9 |
| `sulu_article_excerpt_update` | 3.9 |
| `sulu_article_delete` | 5.4, 5.5 |
| `sulu_snippet_list` | 1.3 |
| `sulu_snippet_get` | 4.5 |
| `sulu_snippet_create` | 4.5 |
| `sulu_snippet_update` | 4.5 |
| `sulu_category_list` | 1.3, 4.2, 5 |
| `sulu_category_create` | 4.2 |
| `sulu_category_delete` | 5.1, 5.2 |
| `sulu_tag_list` | 1.3, 4.1, 5 |
| `sulu_tag_create` | 4.1 |
| `sulu_tag_delete` | 5.3 |
| `sulu_media_list` | 1.3 |
| `sulu_media_get` | 4.4 (if data) |
| `sulu_media_update` | 4.4 (if data) |
| `sulu_contact_list` | 1.3 |
| `sulu_content_search` | 4.3 |
| `sulu_preview_link_generate` | 2.11, 3.6 |
| `sulu_preview_link_revoke` | 2.11, 3.6 |
