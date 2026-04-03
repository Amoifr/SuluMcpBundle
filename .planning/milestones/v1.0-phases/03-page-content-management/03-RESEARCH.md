# Phase 3: Page Content Management - Research

**Researched:** 2026-03-30
**Domain:** Sulu 3.0 Page CRUD via message bus, block management, publishing workflow, MCP tool implementation
**Confidence:** HIGH

## Summary

Phase 3 implements the first content mutation tools in the MCP server bundle. It covers the full page lifecycle: read (get/list/tree), write (create/update/delete), block operations (add/remove/reorder), and publishing (publish/unpublish). The Sulu 3.0 source code has been examined directly -- all message classes, repository interfaces, and controller patterns are verified from the vendor directory.

The key insight is that Sulu 3.0 uses a message bus architecture for all write operations. `CreatePageMessage`, `ModifyPageMessage`, `RemovePageMessage`, and `ApplyWorkflowTransitionPageMessage` are dispatched via `MessageBusInterface` wrapped in `Envelope` with `EnableFlushStamp`. For reading, `PageRepositoryInterface` provides `getOneBy()`, `findBy()`, and `findByAsTree()` with rich filtering. The `ContentManagerInterface` resolves dimension content and normalizes it for output. Block operations are implemented as modifications to the page's data array via `ModifyPageMessage` -- blocks are not independent entities.

**Primary recommendation:** Follow the exact pattern from Sulu's `PageController` (verified in source): use `HandleTrait` pattern for dispatching messages, `PageRepositoryInterface` for reads, `ContentManagerInterface` for content resolution/normalization, and `EnableFlushStamp` for all write operations.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Page CRUD tools dispatch Sulu 3.0 message classes (`CreatePageMessage`, `ModifyPageMessage`, `RemovePageMessage`) via `MessageBusInterface` with `EnableFlushStamp` -- not via REST API or direct Doctrine access
- **D-02:** Page reading uses `ContentResolver` or `ContentManager` to retrieve pages with resolved content and blocks
- **D-03:** All page tools follow the established pattern: `#[McpTool]` attribute, constructor injection of Sulu services, webspace and locale as required parameters
- **D-04:** Block add/remove/reorder (BLCK-01, BLCK-02, BLCK-03) implemented as modifications to the page's content data structure via `ModifyPageMessage` -- blocks are part of the page content payload (JSON columns), not independent entities
- **D-05:** Separate MCP tools for block operations (`sulu_block_add`, `sulu_block_remove`, `sulu_block_reorder`) that internally read current page content, modify the blocks array, then dispatch `ModifyPageMessage`
- **D-06:** BLCK-04 (dynamic block type discovery) is already covered by Phase 2's `sulu://blocks` resource -- Phase 3 does not need to duplicate this
- **D-07:** A `sulu_page_tree` tool exposes the hierarchical page tree as shown in Sulu admin
- **D-08:** Each tree node includes: UUID, title, URL, page type, has-children flag, parent UUID, depth, workflow state, and locale availability
- **D-09:** Accepts webspace and locale parameters. Returns the full tree (not depth-limited)
- **D-10:** Publishing and unpublishing use `ApplyWorkflowTransitionPageMessage` with transition names "publish" and "unpublish"
- **D-11:** Only two workflow states: draft and published
- **D-12:** Keep flat `src/Tool/` directory structure with naming prefix convention
- **D-13:** MCP Prompt deferred from Phase 2 (guideline generation) is in scope for Phase 3

### Claude's Discretion
- Exact `ContentResolver`/`ContentManager` method usage for reading pages (researched below)
- Exact `CreatePageMessage`/`ModifyPageMessage` constructor parameters and content data structure (researched below)
- How page list/search filtering works (researched below)
- Error handling for invalid UUIDs, missing pages, permission violations
- Page tree implementation (`PageRepositoryInterface::findByAsTree()` vs NavigationRepositoryInterface)
- Whether `sulu_page_tree` should support optional depth limiting or subtree queries

### Deferred Ideas (OUT OF SCOPE)
- Page move/copy/reorder operations
- Page version history / restore
- Locale copy for pages
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| PAGE-01 | Get a single page by ID with all content and blocks | `PageRepositoryInterface::getOneBy()` with `GROUP_SELECT_PAGE_ADMIN`, then `ContentManagerInterface::resolve()` + `normalize()` |
| PAGE-02 | List/search pages with filtering by webspace, locale, template | `PageRepositoryInterface::findBy()` with filters: `locale`, `templateKeys`, `parentId`, `tagNames`, `categoryKeys`, pagination via `page`/`limit` |
| PAGE-03 | Create a page with template, title, URL, and content | `CreatePageMessage(webspaceKey, parentId, data)` where data contains `locale`, `template`, `title`, `url`, content fields |
| PAGE-04 | Update page properties and content | `ModifyPageMessage(['uuid' => $id], data)` where data contains `locale` + changed fields |
| PAGE-05 | Delete a page | `RemovePageMessage(['uuid' => $id], locale, forceRemoveChildren)` |
| BLCK-01 | Add a block to a page or article by block type | Read current page content, append block to blocks array in data, dispatch `ModifyPageMessage` |
| BLCK-02 | Remove a block from a page or article | Read current page content, remove block by index from blocks array, dispatch `ModifyPageMessage` |
| BLCK-03 | Reorder blocks on a page or article | Read current page content, reorder blocks array, dispatch `ModifyPageMessage` |
| BLCK-04 | Dynamic discovery of all available block types with field schemas | Already implemented by Phase 2's `BlocksResource` (`sulu://blocks`). No new work needed. |
| PUBL-01 | Publish a page or article | `ApplyWorkflowTransitionPageMessage(['uuid' => $id], locale, 'publish')` |
| PUBL-02 | Unpublish a page or article | `ApplyWorkflowTransitionPageMessage(['uuid' => $id], locale, 'unpublish')` |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- **Verification mandatory:** After code changes, run `composer fix`, `composer lint`, `composer phpstan`, `composer test`
- **Architecture:** Tool classes must not access Doctrine or persistence directly -- delegate to Sulu services
- **Output discipline:** Show minimal diffs only, list modified files explicitly, no refactoring beyond scope
- **One class per file**, `declare(strict_types=1)` required
- **Namespace:** `Sulu\McpServerBundle\{subdomain}`
- **Flat Tool directory:** `src/Tool/` with naming prefix convention (e.g., `PageGetTool.php`, `BlockAddTool.php`)
- **Testing:** Unit tests with PHPUnit mocks (actual codebase pattern -- rules say Prophecy but existing tests use `createMock`), `final` test classes, `#[CoversClass]`
- **No AI attribution in commits**
- **GSD workflow required** before making changes

## Standard Stack

### Core (already installed, no new dependencies)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `sulu/sulu` | ^3.0 | CMS platform | Target platform. Provides PageRepositoryInterface, ContentManagerInterface, message classes |
| `symfony/messenger` | ^7.3 | Message bus | Dispatches CreatePageMessage, ModifyPageMessage etc. with EnableFlushStamp |
| `mcp/sdk` | ^0.4 | MCP Protocol | Provides #[McpTool], #[McpPrompt] attributes |
| `symfony/mcp-bundle` | ^0.6 | MCP Integration | Auto-discovers tools, handles transport |

### Key Sulu Services to Inject

| Service | Interface | Purpose |
|---------|-----------|---------|
| Message Bus | `Symfony\Component\Messenger\MessageBusInterface` | Dispatch all write messages |
| Page Repository | `Sulu\Page\Domain\Repository\PageRepositoryInterface` | Read pages: getOneBy, findBy, findByAsTree, countBy |
| Content Manager | `Sulu\Content\Application\ContentManager\ContentManagerInterface` | Resolve dimension content, normalize for output |
| Webspace Manager | `Sulu\Component\Webspace\Manager\WebspaceManagerInterface` | Validate webspace/locale parameters |
| Security Checker | `Sulu\Component\Security\Authorization\SecurityCheckerInterface` | Permission checks per webspace |

**No new composer dependencies required.** All services are already available in a Sulu 3.0 project.

## Architecture Patterns

### Recommended Project Structure (Phase 3 additions)

```
src/
  Tool/
    PageGetTool.php          # PAGE-01: Get single page by UUID
    PageListTool.php         # PAGE-02: List/search pages
    PageCreateTool.php       # PAGE-03: Create page
    PageUpdateTool.php       # PAGE-04: Update page
    PageDeleteTool.php       # PAGE-05: Delete page
    PageTreeTool.php         # D-07: Hierarchical page tree
    PagePublishTool.php      # PUBL-01: Publish page
    PageUnpublishTool.php    # PUBL-02: Unpublish page
    BlockAddTool.php         # BLCK-01: Add block to page
    BlockRemoveTool.php      # BLCK-02: Remove block from page
    BlockReorderTool.php     # BLCK-03: Reorder blocks on page
  Prompt/
    GuidelineGeneratorPrompt.php  # D-13: Deferred from Phase 2
```

### Pattern 1: Message Bus Dispatch (Write Operations)

**What:** All write operations dispatch Sulu message classes via MessageBusInterface with EnableFlushStamp, using Symfony's HandleTrait to get the return value.

**When to use:** Every create, update, delete, publish, unpublish operation.

**Verified from:** `Sulu\Page\UserInterface\Controller\Admin\PageController` (vendor source)

```php
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;

class PageCreateTool
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        // ... other services
    ) {
    }

    public function createPage(/* ... */): array
    {
        $message = new CreatePageMessage($webspace, $parentId, [
            'locale' => $locale,
            'template' => $template,
            'title' => $title,
            'url' => $url,
            // ... additional content data
        ]);

        /** @var PageInterface $page */
        $page = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        return ['uuid' => $page->getUuid(), /* ... */];
    }
}
```

**Critical details verified from source:**
- `CreatePageMessage` constructor: `(string $webspaceKey, string $parentId, array $data)` -- data MUST contain `locale` key (asserted with Webmozart Assert)
- `ModifyPageMessage` constructor: `(array $identifier, array $data)` -- identifier is `['uuid' => $id]`, data MUST contain `locale` key
- `RemovePageMessage` constructor: `(array $identifier, string $locale, bool $forceRemoveChildren = false)` -- identifier is `['uuid' => $id]`
- `ApplyWorkflowTransitionPageMessage` constructor: `(array $identifier, string $locale, string $transitionName)` -- transitionName is "publish" or "unpublish"
- `HandleTrait` requires the property `$messageBus` (private `MessageBusInterface`) to be set

### Pattern 2: Page Reading (Get Single Page)

**What:** Use PageRepositoryInterface to load the page entity, then ContentManagerInterface to resolve dimension content and normalize for output.

**Verified from:** `PageController::getAction()` (vendor source)

```php
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

// Step 1: Load page entity with dimension content
$page = $this->pageRepository->getOneBy(
    [
        'uuid' => $uuid,
        'locale' => $locale,
        'stage' => DimensionContentInterface::STAGE_DRAFT,
        'loadGhost' => true,
    ],
    [
        PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
    ],
);

// Step 2: Resolve dimension content
$dimensionAttributes = [
    'locale' => $locale,
    'stage' => DimensionContentInterface::STAGE_DRAFT,
];
$dimensionContent = $this->contentManager->resolve($page, $dimensionAttributes);

// Step 3: Normalize to array
$normalized = $this->contentManager->normalize($dimensionContent);
```

### Pattern 3: Page Listing with Filters

**What:** Use PageRepositoryInterface::findBy() for filtered/paginated page lists.

**Verified from:** `PageRepositoryInterface` docblock (vendor source)

```php
// Available filters (from PageRepositoryInterface::findBy docblock):
$filters = [
    'locale' => $locale,
    'stage' => DimensionContentInterface::STAGE_DRAFT,
    'templateKeys' => ['homepage', 'default'],  // filter by template
    'parentId' => $parentUuid,                   // children of specific page
    'tagNames' => ['featured'],                  // filter by tags
    'categoryKeys' => ['news'],                  // filter by categories
    'page' => 1,                                 // pagination
    'limit' => 20,                               // pagination
];

// Available sort options:
$sortBy = ['title' => 'asc'];  // or 'id' => 'asc'/'desc'

$pages = $this->pageRepository->findBy($filters, $sortBy, [
    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
]);

$count = $this->pageRepository->countBy($filters);
```

### Pattern 4: Page Tree

**What:** Use PageRepositoryInterface::findByAsTree() for hierarchical tree output.

**Verified from:** PageRepositoryInterface (vendor source) -- `findByAsTree()` returns nested page iterable.

```php
$pages = $this->pageRepository->findByAsTree(
    [
        'locale' => $locale,
        'stage' => DimensionContentInterface::STAGE_DRAFT,
    ],
    [],
    [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
);
```

**Recommendation:** Use `findByAsTree()` for the page tree tool rather than `NavigationRepositoryInterface`, because:
- `findByAsTree()` returns ALL pages in hierarchy (NavigationRepository only returns pages assigned to navigation contexts)
- The page tree should show the full admin-style tree, not just navigable pages
- Tree includes pages without navigation assignment, drafts, etc.

### Pattern 5: Block Operations (Read-Modify-Write)

**What:** Block tools read the current page content, modify the blocks array, then dispatch ModifyPageMessage with the updated data.

**When to use:** BLCK-01 (add), BLCK-02 (remove), BLCK-03 (reorder)

```php
// 1. Read current page to get existing content
$page = $this->pageRepository->getOneBy(
    ['uuid' => $pageUuid, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
    [PageRepositoryInterface::SELECT_PAGE_CONTENT => [
        'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
        'dimensionAttributes' => ['locale' => $locale, 'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE]],
    ]],
);

$dimensionContent = $this->contentManager->resolve($page, ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT]);
$currentData = $this->contentManager->normalize($dimensionContent);

// 2. Find the block property in the template
// Blocks are stored in a property of type 'block' (e.g., 'content' or 'blocks')
// The AI must specify which property holds the blocks (typically discovered from template metadata)

// 3. Modify the blocks array
$blocks = $currentData['content']['blocks'] ?? [];  // property name varies by template
// Add: array_splice or append
// Remove: unset by index
// Reorder: rearrange array

// 4. Dispatch ModifyPageMessage with updated data
$message = new ModifyPageMessage(
    ['uuid' => $pageUuid],
    [
        'locale' => $locale,
        'template' => $currentData['content']['template'],
        'title' => $currentData['content']['title'],
        'blocks' => $modifiedBlocks,
        // ... include all content fields
    ],
);
$this->handle(new Envelope($message, [new EnableFlushStamp()]));
```

**Important caveat:** The exact content data structure (which key holds blocks, what fields must be included in ModifyPageMessage) depends on the template. The implementer needs to verify by testing with a real Sulu page. The normalized output from ContentManager shows the structure.

### Pattern 6: MCP Prompt (Guideline Generator)

**What:** An MCP Prompt that guides the AI through analyzing existing pages to generate content guidelines.

**Verified from:** `Mcp\Capability\Attribute\McpPrompt` attribute (vendor source)

```php
use Mcp\Capability\Attribute\McpPrompt;

class GuidelineGeneratorPrompt
{
    #[McpPrompt(
        name: 'sulu_generate_guidelines',
        description: 'Guides the AI through analyzing existing pages to generate content guidelines. Use sulu_page_list and sulu_page_get to read pages, then sulu_update_guidelines to save.',
    )]
    public function generateGuidelines(string $webspace, string $locale): array
    {
        // Return prompt messages that instruct the AI to:
        // 1. List pages in the webspace
        // 2. Read several pages to analyze tone, style, audience
        // 3. Synthesize guidelines from the analysis
        // 4. Call sulu_update_guidelines to persist
        return [
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $promptText],
            ]],
        ];
    }
}
```

### Anti-Patterns to Avoid

- **Bypassing message bus:** Never call PageRepository::add() directly for creating pages. Always dispatch CreatePageMessage via message bus. The message handler sets up parent relationships, author, domain events, etc.
- **Direct Doctrine access in tools:** Tools must use PageRepositoryInterface and ContentManagerInterface, never EntityManager or QueryBuilder directly.
- **Forgetting EnableFlushStamp:** All write message dispatches MUST include `new EnableFlushStamp()` in the Envelope stamps. Without it, Doctrine changes are never flushed.
- **Hardcoding block property names:** Different templates have different block properties (could be "content", "blocks", "sections", etc.). Always resolve from template metadata or normalized content.
- **Omitting locale in data array:** Both CreatePageMessage and ModifyPageMessage assert that `data['locale']` exists. Missing it causes a runtime exception.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Page CRUD | Custom Doctrine queries | Sulu message bus (CreatePageMessage etc.) | Messages trigger domain events, audit trail, search indexing, permission checks |
| Page reading | Raw SQL/DQL | PageRepositoryInterface + ContentManagerInterface | Handles dimension content resolution, ghost/shadow pages, locale fallbacks |
| Page tree | Custom nested set queries | PageRepositoryInterface::findByAsTree() | Already handles lft/rgt nested set ordering, depth calculation, has-children flag |
| Block type validation | Custom schema validation | BlocksResource (Phase 2) + template metadata | Runtime discovery from XML templates, always current |
| Content normalization | Custom serializers | ContentManagerInterface::normalize() | Handles all content types including blocks, media references, links |
| Workflow transitions | Custom state machine | ApplyWorkflowTransitionPageMessage | Sulu's workflow handles state validation, permissions, domain events |
| Permission checking | Custom access control | SecurityCheckerInterface with PageAdmin::getPageSecurityContext() | Webspace-scoped, locale-aware, role-based -- already implemented |

## Common Pitfalls

### Pitfall 1: Missing `locale` in Data Array
**What goes wrong:** CreatePageMessage and ModifyPageMessage both assert `$data['locale']` with Webmozart Assert. Forgetting it causes a non-descriptive assertion error.
**Why it happens:** The locale is a tool parameter but also must be duplicated inside the data array.
**How to avoid:** Always merge locale into the data array: `$data = array_merge($data, ['locale' => $locale])`.
**Warning signs:** "Expected a string given. Got: NULL" assertion errors.

### Pitfall 2: CreatePageMessage Requires parentId
**What goes wrong:** `CreatePageMessage` requires a `parentId` parameter. For homepage-level pages, the special value `'homepage'` must be used.
**Why it happens:** Pages are hierarchical. Every non-homepage page must have a parent.
**How to avoid:** Default parentId to the webspace's root page UUID if not specified by the AI. Use `CreatePageMessageHandler::HOMEPAGE_PARENT_ID = 'homepage'` for root pages.
**Warning signs:** Null parent errors during page creation.

### Pitfall 3: Block Read-Modify-Write Race Condition
**What goes wrong:** Two concurrent block operations read the same page, modify different blocks, and the second write overwrites the first's changes.
**Why it happens:** Block operations are not atomic -- they read current state, modify in-memory, then write back the full blocks array.
**How to avoid:** For v1, document this limitation. In tool descriptions, advise against rapid concurrent block operations on the same page. Consider adding optimistic locking via version checking in v2.
**Warning signs:** Blocks appearing to be lost or reverted after concurrent edits.

### Pitfall 4: HandleTrait Property Name
**What goes wrong:** Symfony's HandleTrait expects a private property `$messageBus` of type `MessageBusInterface`. If the property is named differently (e.g., `$bus`), the trait fails silently or throws errors.
**Why it happens:** HandleTrait uses reflection to find the message bus property by name.
**How to avoid:** Always name the constructor parameter and property exactly `$messageBus`.
**Warning signs:** "Unable to find MessageBusInterface" errors at runtime.

### Pitfall 5: Security Context for Pages is Webspace-Scoped
**What goes wrong:** Permission check uses wrong security context string, allowing unauthorized access.
**Why it happens:** Sulu page permissions use `sulu.webspaces.{webspaceKey}` as the security context (verified from `PageAdmin::SECURITY_CONTEXT_PREFIX`). Each webspace has its own permission set.
**How to avoid:** Use `PageAdmin::getPageSecurityContext($webspaceKey)` or construct `'sulu.webspaces.' . $webspaceKey` for permission checks.
**Warning signs:** Permission checks passing when they should fail, or failing for all webspaces.

## Code Examples

### Complete Page Create Tool (verified pattern)

```php
<?php

declare(strict_types=1);

namespace Sulu\McpServerBundle\Tool;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class PageCreateTool
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $content
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_page_create',
        description: 'Create a new page in a webspace. Use sulu://templates resource to discover available templates and their fields before calling this tool.',
    )]
    public function createPage(
        string $webspace,
        string $locale,
        string $template,
        string $title,
        string $parentId,
        ?string $url = null,
        ?array $content = null,
    ): array {
        $data = array_merge(
            $content ?? [],
            [
                'locale' => $locale,
                'template' => $template,
                'title' => $title,
                'url' => $url ?? '/' . \mb_strtolower(\str_replace(' ', '-', $title)),
            ],
        );

        $message = new CreatePageMessage($webspace, $parentId, $data);

        /** @var PageInterface $page */
        $page = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        // Resolve and normalize the created page for response
        $dimensionContent = $this->contentManager->resolve($page, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        return [
            'success' => true,
            'uuid' => $page->getUuid(),
            'data' => $this->contentManager->normalize($dimensionContent),
        ];
    }
}
```

### Message Constructors Reference (verified from source)

```php
// CREATE: new CreatePageMessage(webspaceKey, parentId, data)
// data MUST contain: 'locale' (string, asserted)
// data MAY contain: 'uuid' (pre-generated), 'template', 'title', 'url', content fields, 'author', 'authored'
new CreatePageMessage('example', 'parent-uuid', [
    'locale' => 'en',
    'template' => 'default',
    'title' => 'My Page',
    'url' => '/my-page',
]);

// MODIFY: new ModifyPageMessage(identifier, data)
// identifier: ['uuid' => string]
// data MUST contain: 'locale' (string, asserted)
new ModifyPageMessage(['uuid' => 'page-uuid'], [
    'locale' => 'en',
    'title' => 'Updated Title',
    'url' => '/updated-title',
]);

// REMOVE: new RemovePageMessage(identifier, locale, forceRemoveChildren)
new RemovePageMessage(['uuid' => 'page-uuid'], 'en', false);

// WORKFLOW: new ApplyWorkflowTransitionPageMessage(identifier, locale, transitionName)
// transitionName: 'publish' or 'unpublish'
new ApplyWorkflowTransitionPageMessage(['uuid' => 'page-uuid'], 'en', 'publish');
```

### PageRepositoryInterface Query Reference (verified from source)

```php
// GET ONE:
$page = $this->pageRepository->getOneBy(
    ['uuid' => $id, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT, 'loadGhost' => true],
    [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
);

// FIND MANY (with all available filters):
$pages = $this->pageRepository->findBy(
    [
        'locale' => 'en',
        'stage' => DimensionContentInterface::STAGE_DRAFT,
        'templateKeys' => ['default'],           // optional
        'parentId' => 'parent-uuid',             // optional
        'tagNames' => ['featured'],              // optional
        'categoryKeys' => ['news'],              // optional
        'page' => 1,                              // optional pagination
        'limit' => 20,                            // optional pagination
    ],
    ['title' => 'asc'],                          // optional sort
    [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
);

// TREE:
$tree = $this->pageRepository->findByAsTree(
    ['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT],
    [],
    [PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true],
);

// COUNT:
$total = $this->pageRepository->countBy(['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT]);
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 or ^11.5 |
| Config file | `phpunit.xml.dist` |
| Quick run command | `composer test -- --filter PageTool` |
| Full suite command | `composer test` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PAGE-01 | Get single page by UUID | unit | `composer test -- --filter PageGetToolTest` | Wave 0 |
| PAGE-02 | List/search pages with filters | unit | `composer test -- --filter PageListToolTest` | Wave 0 |
| PAGE-03 | Create page with template, title, URL | unit | `composer test -- --filter PageCreateToolTest` | Wave 0 |
| PAGE-04 | Update page properties and content | unit | `composer test -- --filter PageUpdateToolTest` | Wave 0 |
| PAGE-05 | Delete page | unit | `composer test -- --filter PageDeleteToolTest` | Wave 0 |
| BLCK-01 | Add block by type | unit | `composer test -- --filter BlockAddToolTest` | Wave 0 |
| BLCK-02 | Remove block | unit | `composer test -- --filter BlockRemoveToolTest` | Wave 0 |
| BLCK-03 | Reorder blocks | unit | `composer test -- --filter BlockReorderToolTest` | Wave 0 |
| BLCK-04 | Block type discovery | n/a | Already covered by Phase 2 `BlocksResource` tests | Existing |
| PUBL-01 | Publish page | unit | `composer test -- --filter PagePublishToolTest` | Wave 0 |
| PUBL-02 | Unpublish page | unit | `composer test -- --filter PageUnpublishToolTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `composer test -- --filter "Page\|Block"` (phase-relevant tests only)
- **Per wave merge:** `composer test && composer phpstan && composer lint`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/Tool/PageGetToolTest.php` -- covers PAGE-01
- [ ] `tests/Unit/Tool/PageListToolTest.php` -- covers PAGE-02
- [ ] `tests/Unit/Tool/PageCreateToolTest.php` -- covers PAGE-03
- [ ] `tests/Unit/Tool/PageUpdateToolTest.php` -- covers PAGE-04
- [ ] `tests/Unit/Tool/PageDeleteToolTest.php` -- covers PAGE-05
- [ ] `tests/Unit/Tool/PageTreeToolTest.php` -- covers D-07
- [ ] `tests/Unit/Tool/PagePublishToolTest.php` -- covers PUBL-01
- [ ] `tests/Unit/Tool/PageUnpublishToolTest.php` -- covers PUBL-02
- [ ] `tests/Unit/Tool/BlockAddToolTest.php` -- covers BLCK-01
- [ ] `tests/Unit/Tool/BlockRemoveToolTest.php` -- covers BLCK-02
- [ ] `tests/Unit/Tool/BlockReorderToolTest.php` -- covers BLCK-03
- [ ] `tests/Unit/Prompt/GuidelineGeneratorPromptTest.php` -- covers D-13

## Open Questions

1. **Exact content data structure for ModifyPageMessage**
   - What we know: The data array must contain `locale`. Content fields are passed flat alongside metadata (template, title, url).
   - What's unclear: The exact key names for block content in the data array depend on the template's XML definition. The PageMapper interface maps data to dimension content -- the exact mapping depends on registered mappers.
   - Recommendation: During implementation, create a test page via the tool and inspect what `ContentManagerInterface::normalize()` returns. Use that structure as the reference for the data array. Consider exposing a "page data structure" debug endpoint during development.

2. **findByAsTree() return structure**
   - What we know: Returns `iterable<PageInterface>` with nested children accessible via `getChildren()`.
   - What's unclear: Exact tree serialization format for MCP response. Need to traverse and build node objects.
   - Recommendation: Implement a recursive serializer that walks the tree and builds the D-08 node format. Test with real Sulu data.

3. **Block property name in page content**
   - What we know: Blocks are stored in a property of type "block" in the template. The property name varies by template (commonly "content" or "blocks").
   - What's unclear: Whether ModifyPageMessage expects the blocks under the property name or at a flat level.
   - Recommendation: Use the existing `sulu://templates` resource to discover which property in a template is of type "block". The block tools should accept the property name as a parameter (with a default of "blocks" or auto-detection from template).

## Sources

### Primary (HIGH confidence)
- `vendor/sulu/sulu/packages/page/src/Application/Message/CreatePageMessage.php` -- constructor verified: (webspaceKey, parentId, data) with locale assertion
- `vendor/sulu/sulu/packages/page/src/Application/Message/ModifyPageMessage.php` -- constructor verified: (identifier, data) with locale assertion
- `vendor/sulu/sulu/packages/page/src/Application/Message/RemovePageMessage.php` -- constructor verified: (identifier, locale, forceRemoveChildren)
- `vendor/sulu/sulu/packages/page/src/Application/Message/ApplyWorkflowTransitionPageMessage.php` -- constructor verified: (identifier, locale, transitionName)
- `vendor/sulu/sulu/packages/page/src/UserInterface/Controller/Admin/PageController.php` -- verified dispatch pattern with HandleTrait, EnableFlushStamp, getAction/postAction/putAction/deleteAction
- `vendor/sulu/sulu/packages/page/src/Domain/Repository/PageRepositoryInterface.php` -- verified filter/sort/select signatures with full docblock types
- `vendor/sulu/sulu/packages/content/src/Application/ContentManager/ContentManagerInterface.php` -- verified resolve/persist/normalize/applyTransition
- `vendor/sulu/sulu/packages/page/src/Application/MessageHandler/CreatePageMessageHandler.php` -- verified parentId handling, author defaults
- `vendor/sulu/sulu/packages/page/src/Infrastructure/Sulu/Admin/PageAdmin.php` -- verified security context: `sulu.webspaces.{key}`
- `vendor/mcp/sdk/src/Capability/Attribute/McpTool.php` -- verified attribute signature
- `vendor/mcp/sdk/src/Capability/Attribute/McpPrompt.php` -- verified attribute signature
- Existing codebase: `src/Tool/PingTool.php`, `src/Tool/UpdateGuidelinesTool.php`, `src/Resource/BlocksResource.php` -- established patterns

### Secondary (MEDIUM confidence)
- `.planning/research/STACK.md` -- project research document, verified against source
- `.planning/research/ARCHITECTURE.md` -- project architecture document
- `.planning/research/PITFALLS.md` -- project pitfalls document

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all services verified from vendor source code
- Architecture: HIGH -- message dispatch pattern and repository API verified from Sulu's own PageController
- Pitfalls: HIGH -- constructor signatures verified, security context pattern verified
- Block operations: MEDIUM -- read-modify-write pattern is clear but exact data structure for ModifyPageMessage block content needs runtime verification

**Research date:** 2026-03-30
**Valid until:** 2026-04-30 (stable -- Sulu 3.0 released, interfaces unlikely to change)
