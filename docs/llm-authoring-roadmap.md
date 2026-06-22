# LLM Authoring Ergonomics — Roadmap

> Status: **largely implemented** as of the latest work — see "Implementation status" below.
> Audience: maintainers of the Sulu MCP Server bundle.

## Implementation status

- ✅ **Tool consolidation** — block tools unified 8→5, generic over page/article/snippet (`ContentTypeResolver`); cheap wins (`blockData` flat object, `block_add` returns `_id`).
- ✅ **P0 first-class authoring** — `content` on page/article create/update accepts a full nested `blocks` tree; `_id`s auto-assigned (`assignBlockIds`), blocks recursively validated, and **required fields enforced** (`BlockDataValidator::validateContentTree`).
- ✅ **Excerpt + SEO in one call** — via `ContentMetadataMapper` on create/update; standalone `*_seo_*` / `*_excerpt_*` tools removed; reads included in `*_get`, discovery via `sulu_get_context` (`seoFields` / `excerptFields`).
- ✅ **Snippet create/update** — `sulu_snippet_create` / `sulu_snippet_update` (snippets have no SEO/excerpt; nested blocks supported).
- ✅ **Value examples in `get_context`** — scalar field types carry a `valueExample`, and `text_editor` explains internal links via `<sulu-link href="<uuid>" provider="page">` (`FieldValueExampleProvider`). Complex selection types (`media_selection`, `smart_content`, `*_selection`) are intentionally omitted for now — no example rather than a wrong guess.
- ✅ **Id-based reorder** — `sulu_block_reorder` accepts `blockIds` (ordered `_id` list) as a robust alternative to `newOrder` indices.

## Context

The primary use case for this MCP server is **conversational draft authoring**:

> A human discusses a topic with an LLM agent. When the outline feels right, they
> tell the agent to create the draft. The agent should then fill the template
> *correctly* — template fields, property types, and blocks (including nested
> block-in-block) — in as few, as reliable steps as possible.

A full hands-on session (create page → add one block of every type → update all
blocks → reorder → match a reference page's order → publish → fill SEO/excerpt)
plus two independent LLM reviews converged on the same conclusion:

**The foundation is solid. The weak point is LLM *ergonomics*, not Sulu capability.**
The server is capable, but the agent has to be "too clever": it chains many
stateful mutations, guesses undocumented value shapes, and recovers from schema
mismatches manually.

### Guiding principle

Make **whole-draft authoring the happy path.** Keep the per-block tools
(`block_add`, `block_update`, `block_reorder`) as **refinement** tools, not the
primary interface for "create the whole article from our discussion."

## Key finding (the cheap unlock)

One-shot authoring is *closer than it looks*, because the recursive machinery
already exists — it is simply not wired into the create/update path.

- `sulu_page_update` accepts a full `blocks` array today: `content` is
  `Schema(type: 'object', additionalProperties: true)` and is merged wholesale
  over current state (`src/Capabilities/Tool/Page/PageUpdateTool.php:86`). Passing
  a `blocks` key **replaces the entire block array**.
- This works for **editing/reordering an existing tree** (the blocks already
  carry `_id`s that you read back first). It is **not safe for authoring new
  blocks**, because:
  - Neither `PageUpdateTool` nor `PageCreateTool` ever calls `assignBlockIds`
    (`PageCreateTool` does not even import `BlockDataNormalizerTrait`). New blocks
    get no stable `_id`.
  - Nothing recursively validates block values or required fields on this path.
  - Tool descriptions frame `content` as a *flat field map*, so the capability is
    invisible.
- The recursion you would need is already implemented:
  `BlockDataNormalizerTrait::assignBlockIds()`
  (`src/Capabilities/Tool/BlockDataNormalizerTrait.php:27-44`) already walks
  nested lists and stamps an `_id` on every array with a `type`.

So "first-class full-draft authoring" is mostly a **wiring + recursive-validation**
job on top of a small shared service — not new infrastructure.

> Correction to earlier framing: "impossible to author in one call" is too strong.
> Accurate statement: **works for editing/reordering an existing tree; not
> safe/reliable as the intended one-shot authoring path.**

## Prioritized roadmap

### P0 — First-class full-draft authoring

**Problem.** No reliable single call to materialize a complete draft (template
fields + blocks + nested blocks + excerpt + SEO) with stable ids and validation.

**Proposed change.** Introduce a shared internal **draft-assembler service** that:
1. recursively assigns `_id` to every block (reuse `assignBlockIds`),
2. recursively validates block *values* and required fields against the template
   schema (extend `BlockDataValidator`, which today checks field *names* only),
3. writes the draft in one operation (fields + nested blocks + excerpt + SEO).

Expose it via one of the two shapes below (see **Open decision**).

**Effort:** M (machinery largely exists; cost is recursive validation + wiring).

**Acceptance criteria.**
- One call creates a page/article whose new + nested blocks all have stable `_id`s.
- Missing required fields / invalid block keys / invalid nested block types are
  rejected *before* any write, with actionable messages.
- Excerpt + SEO can be set in the same call.
- Existing per-block tools continue to work unchanged for refinement.

### P1 — Fix `blockData` schema mismatch

**Problem.** `BlockAddTool.blockData` has **no `#[Schema]`** (inferred as array;
must be array-wrapped client-side) while `BlockUpdateTool.blockData` is explicit
`Schema(type: 'object')`. The sharpest live failure; symptoms vary by connector.

**Evidence.** `src/Capabilities/Tool/Page/BlockAddTool.php:52-53`,
`src/Capabilities/Tool/Page/BlockUpdateTool.php:54-56`.

**Proposed change.** Make **both** accept the same flat object shape:
`blockData: {"content": "<p>…</p>"}`. Keep the existing list-unwrap leniency
(`normalizeBlockData`) for back-compat.

**Effort:** S.

### P2 — Return created block summary from `block_add`

**Problem.** `block_add` returns `{success, uuid, blockCount, addedAt}` — no `_id`
(`src/Capabilities/Tool/Page/BlockAddTool.php:128-133`). Any nested follow-up
forces a `block_list` round-trip to discover the id.

**Proposed change.** Return at least `_id`, `type`, `index`, and ideally the full
normalized block.

**Effort:** S.

### P2 — Value examples / schema in `get_context`

**Problem.** `get_context` exposes only `{name, type, label, required}` per field
(`src/Capabilities/Resource/TemplatesResource.php:82-88`,
`src/Capabilities/Resource/BlocksResource.php:101-105`). The *value shape* for rich
property types is undocumented — `media_selection` (`{"ids":[1]}`) was learned by
trial, and two different agents guessed two different shapes; `smart_content` is
effectively un-fillable blind.

**Proposed change.** Add a `valueExample` (or value-schema) per field type for at
least: `media_selection`, `smart_content`, `single_select`, `category_selection`,
`tag_selection`, `route` / `page_tree_route`, and nested `block` properties.

**Effort:** S–M.

### P2 — Id-based reorder

**Problem.** `newOrder` accepts integer indices only
(`src/Capabilities/Tool/Page/BlockReorderTool.php:46`; article variant identical).
Indices shift as blocks are added/removed; LLMs reason better over stable ids.

**Proposed change.** Accept `blockIds: [...]` as an alternative to integer indices.

**Effort:** S–M.

### P3 — Strict recursive value validation

**Problem.** `BlockDataValidator` validates field *names* (keys) but not *values*,
and does not recurse into nested block values. Bad values pass MCP validation and
may fail deeper in Sulu.

**Proposed change.** Validate nested block values + required fields recursively
(shared with P0's assembler and the optional validate tool).

**Effort:** M.

### P3 — Optional: pre-write validate / dry-run tool

**Problem.** No way to check a full draft payload before mutating.

**Proposed change.** `validate_page_draft` / `validate_article_draft` that share
P0's exact schema + validation path and report missing required fields, invalid
block keys, wrong media shapes, invalid nested block types, and route conflicts —
without writing.

**Effort:** M.

## Metadata gaps (conscious decisions)

- **SEO and excerpt read-back is included in `sulu_page_get` / `sulu_article_get`** — both
  now return `seo` and `excerpt` keys alongside the main content. Field names are
  project-specific; call `sulu_get_context` to discover them via `seoFields` /
  `excerptFields`. No separate get tools are needed.
- **SEO and excerpt writes go through create/update** — pass `seo` / `excerpt` objects
  to `sulu_page_create` / `sulu_page_update` / `sulu_article_create` /
  `sulu_article_update`. No separate update tools exist.
- **Excerpt taxonomy is out of scope today.** Categories, tags, audience target
  groups, and segment are not settable; `excerptAudienceTargetGroups` and
  `excerptSegment` are stripped from responses. `category_*` and `tag_*` tools
  exist but cannot be *attached* to content. **Decision needed:**
  add page/article tag/category assignment tools, or document taxonomy as
  explicitly out of scope.

## Decision — authoring API shape (P0): Option A (extend create/update)

Both options sit on the same draft-assembler service; the difference is the tool
surface above it.

| | A — extend create/update | B — dedicated `*_draft_fill` |
|---|---|---|
| Tool count | No new tools | +2 (fill) [+2 if validate] |
| Intent clarity | One path, but `content` overloaded | Names the intent exactly |
| Semantics | Mixes patch (`title`) + replace (`blocks`) | Can be strict full-replace |
| Existing path | The already-used update-with-blocks becomes safe | create/update stay lenient/simple |
| Validate sibling | Awkward (overloads create/update) | Natural (`validate_*_draft`) |

**Resolved: Option A.** Maintainers prefer a **smaller** total tool surface (see
Tool consolidation below), which removes B's main advantage (intent clarity) as a
reason to add tools. Extend create/update so `content` is a first-class authoring
tree (assign ids recursively + validate recursively); fold excerpt/SEO into the
same call. The already-used `update`-with-`blocks` path becomes *safe* for new
blocks as a direct result.

Mitigate Option A's two known costs explicitly:
- **Overloaded `content`:** document the accepted tree shape in the tool
  description + `get_context` value examples (see P2).
- **Patch-vs-replace asymmetry:** `title` patches one field, but a `blocks` key
  replaces the whole array. State this loudly in the description; consider a
  `blocksMode: patch|replace` only if real usage demands it.

## Tool consolidation

**Principle: consolidate by *content-type*, not by *operation*.**

- **By content-type (do it — free):** dispatch `page` / `article` / `snippet`
  through a `type` param. Schemas are identical; already proven for block
  list/update. No ergonomic cost.
- **By operation (avoid — costly):** merging operations into one tool with an
  `action` enum reintroduces conditional-required-field ambiguity that a single
  JSON schema cannot express — the same ergonomics problem this roadmap exists to
  fix. Keep operations as separate, fully-specified tools.

### Blocks (current: 8 tools, inconsistently consolidated)

`BlockListTool` and `BlockUpdateTool` are already `type`-generic (hence no
`ArticleBlockList`/`ArticleBlockUpdate`). But `BlockAdd` / `BlockRemove` /
`BlockReorder` each have a Page **and** an `Article*` duplicate
(`src/Capabilities/Tool/Article/ArticleBlock{Add,Remove,Reorder}Tool.php`) — 6
tools doing the work of 3.

| Stance | Tools | Set |
|--------|-------|-----|
| **Conservative (recommended first)** | 4 | `block_list`, `block_add`, `block_update`, `block_remove` — all `type`-generic; reorder handled by `update`-with-`blocks` |
| Aggressive (optional follow-up) | 2–3 | `block_list` (read) + `block_mutate` (add/update/remove keyed on `blockId` presence) [+ `block_reorder` if retained] |

Notes:
- Conservative removes 4 tools (3 `Article*` duplicates + standalone reorder) with
  no schema ambiguity.
- `block_mutate` is defensible (params overlap heavily: `type`, `uuid`,
  `blockProperty`, `blockData`, `blockId`) but should **only** merge
  add/update/remove — never fold in `reorder` or `list`.
- Once P0 lands, the per-block tools are *refinement* conveniences for
  token-efficient single-block edits on large pages, not the primary authoring
  interface.

### ✅ Top-level page/article tools (largest lever — completed)

Completed by the toolset-simplification refactor: `delete` / `publish` / `unpublish` unified into `sulu_content_*`; standalone `seo_get/update` / `excerpt_get/update` tools removed in favor of reads via `*_get` and writes through create/update parameters.

## Suggested sequencing

**Consolidate before the authoring refactor.** Consolidation produces the shared,
`type`-generic block core that P0 builds on. Doing P0 first means adding
id-assignment + validation to still-duplicated Page/Article tools and re-merging
them later (rework).

1. **Phase 1 — Consolidate + cheap wins** (no open decisions, low risk):
   - Extract a shared `type`-generic block handler/service — the future home of
     id-assignment + recursive validation.
   - Unify `add` / `remove` / `reorder` under `type`; drop the 3 `Article*`
     duplicates (8 → 5 block tools).
   - Bundle into the same pass: **P1** (unify `blockData` to a flat object) and
     **P2** (`block_add` returns `_id`/summary) — same files, one pass.
   - Tests green; ship independently.
2. **Phase 2 — P0 first-class authoring** (the refactor):
   - Add recursive id-assignment (reuse `assignBlockIds`) + recursive
     value/required validation to the shared handler.
   - Wire it into create/update `content`; fold excerpt/SEO into the one call.
   - Result: `update`-with-`blocks` becomes safe for new + nested blocks.
3. **Phase 3 — Polish & remaining decisions**:
   - Decide `reorder`'s fate (fold into `update`-with-`blocks` → 5 → 4) and
     id-ordering placement.
   - **P2** `get_context` value examples.
   - Optional aggressive merge (`block_mutate` → 2–3) and validate/dry-run tool
     (**P3**).
4. **Phase 4 — Largest lever (later)**: collapse the top-level page/article
   duplication into generic `sulu_content_*` tools.

## Target workflow (end state)

```
get_context → discuss content → (validate_*_draft) → *_draft_fill (full payload)
            → optional per-block refinement → explicit publish
```
