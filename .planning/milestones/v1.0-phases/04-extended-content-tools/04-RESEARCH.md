# Phase 4: Extended Content Tools - Research

**Researched:** 2026-03-31
**Domain:** Sulu 3.0 content management -- articles, taxonomy, media, read-only entities
**Confidence:** HIGH

## Summary

Phase 4 extends the MCP tool surface from pages (Phase 3) to all remaining Sulu content types: articles, tags, categories, media, snippets, contacts, and navigation. The research confirms two distinct service patterns: (1) hexagonal message bus for articles/snippets (same as pages), and (2) traditional bundle manager interfaces for tags, categories, and media.

The critical finding is that `CreateArticleMessage` takes only a flat `$data` array -- no webspace, no parentId. Articles are flat entities (no hierarchy) and not scoped to a webspace at creation time. This is a significant difference from the page pattern. All other article messages (`ModifyArticleMessage`, `RemoveArticleMessage`, `ApplyWorkflowTransitionArticleMessage`) follow the same identifier+data/locale pattern as their page counterparts.

Navigation in Sulu 3.0 lives in a new `NavigationRepositoryInterface` in the `Sulu\Page\Domain\Repository` namespace (hexagonal package), not the legacy WebsiteBundle. Tag listing requires `TagRepositoryInterface` (extends Doctrine `ObjectRepository` with `findAll()`), not the `TagManagerInterface` which only has single-entity operations.

**Primary recommendation:** Mirror the Phase 3 page tool pattern exactly for articles (with the noted constructor differences), use traditional manager/repository calls for taxonomy and media, and use `NavigationRepositoryInterface` for navigation trees.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- D-01: Article tools mirror page tool pattern: dispatch Sulu 3.0 message classes via MessageBusInterface with EnableFlushStamp, read via ContentManagerInterface resolve/normalize
- D-02: Articles are flat (no parent hierarchy) -- no ArticleTreeTool. Article list tool uses flat pagination with type/template filtering
- D-03: Article create takes a `type` parameter (e.g., "default", "blog") instead of parentId
- D-04: Article publish/unpublish uses ApplyWorkflowTransitionArticleMessage with "publish"/"unpublish" transitions
- D-05: Article webspace semantics TBD by researcher -- CreateArticleMessage constructor needs verification
- D-06: Separate article-specific block tools (ArticleBlockAddTool, ArticleBlockRemoveTool, ArticleBlockReorderTool) -- not polymorphic refactoring
- D-07: Article block tools use ModifyArticleMessage internally (read-modify-dispatch pattern)
- D-08: Same blockProperty parameter pattern as page block tools
- D-09: Tags and categories use traditional Sulu bundle managers (TagManagerInterface, CategoryManagerInterface) with direct PHP calls -- NOT message bus
- D-10: Tag tools: TagCreateTool, TagListTool, TagDeleteTool via TagManagerInterface
- D-11: Category tools: CategoryCreateTool, CategoryListTool, CategoryDeleteTool -- CategoryListTool returns tree structure
- D-12: Categories are hierarchical (tree structure) -- list tool must return parent/child relationships
- D-13: Media tools use MediaManagerInterface with direct PHP calls -- NOT message bus
- D-14: MediaListTool with filtering by collection, type, tags
- D-15: MediaGetTool returns metadata, URLs, dimensions, format URLs
- D-16: MediaUpdateTool for alt text, title, copyright via MediaManagerInterface::save()
- D-17: No media upload in v1 -- list, get details, update metadata only
- D-18: Snippets use ContentManager resolve/normalize pattern (hexagonal packages)
- D-19: Navigation uses Sulu's navigation service -- returns navigation tree structures per webspace/locale
- D-20: Contacts/accounts use traditional repository interfaces. Tools should be conditional on ContactBundle presence
- D-21: All read-only tools are GET-only -- no create/update/delete
- D-22: Plan split: Plan 04-01 = Article CRUD + article blocks + article publishing (message bus pattern). Plan 04-02 = Taxonomy + media + read-only entities (manager interface pattern)

### Claude's Discretion
- Exact CreateArticleMessage/ModifyArticleMessage constructor parameters (RESOLVED -- see Architecture Patterns)
- ArticleRepositoryInterface query methods for list/search (RESOLVED -- see Architecture Patterns)
- CategoryManagerInterface tree retrieval approach (RESOLVED -- see Architecture Patterns)
- MediaManagerInterface list/search query API (RESOLVED -- see Architecture Patterns)
- Whether snippet reading needs webspace parameter or is global (RESOLVED -- see Architecture Patterns)
- Contact/account repository interface names and query methods (RESOLVED -- see Architecture Patterns)
- Navigation service interface name and tree format (RESOLVED -- see Architecture Patterns)
- Error handling for optional bundles (ContactBundle not installed)

### Deferred Ideas (OUT OF SCOPE)
- Media upload from URL -- v2 (EXTD-02)
- Snippet CRUD (create, update, delete) -- v2 (EXTD-01)
- Navigation write operations -- v2 (EXTD-03)
- Article move/copy/locale copy operations -- future
- Article version restore -- future
- Locale-aware translation suggestions -- v2 (EXTD-04)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ARTC-01 | Get a single article by ID with all content | ArticleRepositoryInterface::getOneBy() + ContentManager resolve/normalize -- same pattern as PageGetTool |
| ARTC-02 | List/search articles with filtering | ArticleRepositoryInterface::findBy() supports templateKeys, categoryIds, tagNames, page/limit filters |
| ARTC-03 | Create an article with type, title, and content | CreateArticleMessage(array $data) -- flat data array with locale required, no webspace/parentId |
| ARTC-04 | Update article properties and content | ModifyArticleMessage(array $identifier, array $data) -- same pattern as ModifyPageMessage |
| ARTC-05 | Delete an article | RemoveArticleMessage(array $identifier, string $locale) -- same pattern as RemovePageMessage |
| TAXO-01 | Create a tag | TagManagerInterface::save(['name' => $name]) |
| TAXO-02 | List tags | TagRepositoryInterface::findAll() (extends Doctrine ObjectRepository) |
| TAXO-03 | Delete a tag | TagManagerInterface::delete($id) |
| TAXO-04 | Create a category | CategoryManagerInterface::save($data, $userId, $locale) |
| TAXO-05 | List categories (tree structure) | CategoryManagerInterface::findChildrenByParentId(null) returns full tree, getApiObjects() wraps for output |
| TAXO-06 | Delete a category | CategoryManagerInterface::delete($id) |
| MDIA-01 | List/search media with filtering | MediaManagerInterface::get($locale, $filter, $limit, $offset) with filter keys: collection, types, search, ids |
| MDIA-02 | Get media details | MediaManagerInterface::getById($id, $locale) returns Api\Media with getId(), getTitle(), getMimeType(), getSize(), getUrl(), formats |
| MDIA-03 | Update media metadata | MediaManagerInterface::save(null, $data, $userId) with data['id'] set triggers update path |
| READ-01 | Get/list contacts and accounts | ContactRepositoryInterface::findGetAll($limit, $offset, $sorting, $where), AccountRepositoryInterface::findByIds() |
| READ-02 | Get/list snippets with content | SnippetRepositoryInterface::getOneBy()/findBy() + ContentManager resolve/normalize -- identical to page/article pattern |
| READ-03 | Get navigation structures | NavigationRepositoryInterface::getNavigationTree($context, $locale, $webspaceKey, $segmentKey, $depth) |
</phase_requirements>

## Architecture Patterns

### Resolved Research Questions (from D-05 / Claude's Discretion)

#### 1. CreateArticleMessage Constructor Parameters
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/packages/article/src/Application/Message/CreateArticleMessage.php
class CreateArticleMessage
{
    public function __construct(array $data)
    // Asserts: $data['locale'] must be string
    // Optional: $data['uuid']
}
```

**Key difference from CreatePageMessage:** No `$webspaceKey`, no `$parentId` parameters. Articles are flat, webspace-agnostic entities. The `type` field (e.g., "default", "blog") goes into the `$data` array alongside `locale`, `template`, `title`, etc.

**ArticleCreateTool signature should be:**
```php
public function createArticle(
    string $locale,
    string $template,
    string $title,
    ?string $type = null,     // article type, optional
    ?array $content = null,
): array
```

#### 2. ArticleRepositoryInterface Query Methods
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/packages/article/src/Domain/Repository/ArticleRepositoryInterface.php
interface ArticleRepositoryInterface
{
    const GROUP_SELECT_ARTICLE_ADMIN = 'article_admin';

    public function getOneBy(array $filters, array $selects = []): ArticleInterface;
    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable;
    public function countBy(array $filters = []): int;
}
```

**Supported filters for findBy():**
- `uuid`, `uuids`, `locale`, `stage` -- basic filters
- `categoryIds`, `categoryKeys`, `categoryOperator` ('AND'|'OR') -- category filtering
- `tagIds`, `tagNames`, `tagOperator` ('AND'|'OR') -- tag filtering
- `templateKeys` -- template/type filtering
- `page`, `limit` -- pagination

This is the same interface shape as `PageRepositoryInterface` and `SnippetRepositoryInterface`.

#### 3. CategoryManagerInterface Tree Retrieval
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/src/Sulu/Bundle/CategoryBundle/Category/CategoryManagerInterface.php
public function findChildrenByParentId($parentId = null);  // returns full tree when null
public function getApiObjects($categories, $locale);       // wraps entities in API objects
```

Use `findChildrenByParentId(null)` to get the full tree, then `getApiObjects()` to get locale-aware representations with `getId()`, `getName()`, `getKey()`, `getChildren()`.

#### 4. MediaManagerInterface List/Search API
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/src/Sulu/Bundle/MediaBundle/Media/Manager/MediaManagerInterface.php
public function get($locale, $filter = [], $limit = null, $offset = null);
public function getCount();  // returns count from last get() call
public function getById($id, $locale);
public function save($uploadedFile, $data, $userId);  // null uploadedFile + data['id'] = metadata update
```

**Filter keys for get()** (from MediaRepository::extractFilterVars):
- `collection` -- collection ID (integer)
- `types` -- media type IDs (array)
- `search` -- text search string
- `ids` -- specific media IDs (array)
- `orderBy`, `orderSort` -- sorting
- `systemCollections` -- include system collections (default true)

**No tag filtering at the repository level.** Tag filtering for media requires post-filtering or a different approach. The MDIA-01 requirement for tag filtering may need to use the `search` parameter or document this limitation.

#### 5. Snippet Reading -- Webspace Scoping
**Confidence: HIGH** (verified from vendor source code)

Snippets are **global** -- not webspace-scoped. `SnippetRepositoryInterface` has no webspace filter in its `findBy()` or `getOneBy()` methods. The filters match articles exactly: `locale`, `stage`, `templateKeys`, `categoryIds`, `tagNames`, `page`, `limit`.

The SnippetGetTool and SnippetListTool should take `locale` as parameter but NOT `webspace`.

#### 6. Contact/Account Repository Interfaces
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/src/Sulu/Bundle/ContactBundle/Entity/ContactRepositoryInterface.php
interface ContactRepositoryInterface extends RepositoryInterface
{
    public function findById($id);
    public function findByIds($ids);
    public function findGetAll($limit = null, $offset = null, $sorting = [], $where = []);
}

// Source: vendor/sulu/sulu/src/Sulu/Bundle/ContactBundle/Entity/AccountRepositoryInterface.php
interface AccountRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?AccountInterface;
    public function findByIds(array $ids): array;
    public function findAllSelect(array $fields = []): array;
}
```

Both extend `RepositoryInterface` which extends Doctrine `ObjectRepository` (provides `findAll()`, `findBy()`, etc.).

**ContactBundle is present in Sulu 3.0** (verified in vendor). However, tools should use service container check for robustness since ContactBundle could theoretically be removed from a custom Sulu installation.

#### 7. Navigation Service Interface
**Confidence: HIGH** (verified from vendor source code)

```php
// Source: vendor/sulu/sulu/packages/page/src/Domain/Repository/NavigationRepositoryInterface.php
// Namespace: Sulu\Page\Domain\Repository\NavigationRepositoryInterface
interface NavigationRepositoryInterface
{
    public function getNavigationTree(
        string $navigationContext,
        string $locale,
        string $webspaceKey,
        ?string $segmentKey,
        int $depth = 1,
        array $properties = []
    ): array;

    public function getNavigationFlat(...): array;
    public function getBreadcrumb(string $uuid, string $locale, string $webspaceKey, ...): array;
}
```

**This is in the hexagonal `packages/page` namespace**, not the legacy WebsiteBundle. The `$navigationContext` parameter is a string key defined in the webspace XML config (e.g., "main", "footer"). The tool should accept webspace, locale, and navigationContext as parameters.

### Recommended Tool Structure

All new tools go in `src/Tool/` following the flat directory convention.

**Plan 04-01 (Message Bus Pattern):**
```
src/Tool/
├── ArticleGetTool.php          # ARTC-01
├── ArticleListTool.php         # ARTC-02
├── ArticleCreateTool.php       # ARTC-03
├── ArticleUpdateTool.php       # ARTC-04
├── ArticleDeleteTool.php       # ARTC-05
├── ArticlePublishTool.php      # PUBL-01 (articles)
├── ArticleUnpublishTool.php    # PUBL-02 (articles)
├── ArticleBlockAddTool.php     # BLCK-01 (articles)
├── ArticleBlockRemoveTool.php  # BLCK-02 (articles)
└── ArticleBlockReorderTool.php # BLCK-03 (articles)
```

**Plan 04-02 (Manager Interface Pattern):**
```
src/Tool/
├── TagCreateTool.php           # TAXO-01
├── TagListTool.php             # TAXO-02
├── TagDeleteTool.php           # TAXO-03
├── CategoryCreateTool.php      # TAXO-04
├── CategoryListTool.php        # TAXO-05
├── CategoryDeleteTool.php      # TAXO-06
├── MediaListTool.php           # MDIA-01
├── MediaGetTool.php            # MDIA-02
├── MediaUpdateTool.php         # MDIA-03
├── SnippetGetTool.php          # READ-02
├── SnippetListTool.php         # READ-02
├── ContactListTool.php         # READ-01
├── NavigationGetTool.php       # READ-03
└── (AccountListTool.php)       # READ-01 (optional, may combine with ContactListTool)
```

### Pattern 1: Article CRUD (Message Bus)

Mirrors page pattern exactly with these differences:

| Aspect | Page | Article |
|--------|------|---------|
| Create message | `CreatePageMessage($webspace, $parentId, $data)` | `CreateArticleMessage($data)` |
| Modify message | `ModifyPageMessage($identifier, $data)` | `ModifyArticleMessage($identifier, $data)` |
| Remove message | `RemovePageMessage($identifier, $locale, $forceChildren)` | `RemoveArticleMessage($identifier, $locale)` |
| Workflow message | `ApplyWorkflowTransitionPageMessage($id, $locale, $transition)` | `ApplyWorkflowTransitionArticleMessage($id, $locale, $transition)` |
| Repository | `PageRepositoryInterface` | `ArticleRepositoryInterface` |
| Repository group | `GROUP_SELECT_PAGE_ADMIN` | `GROUP_SELECT_ARTICLE_ADMIN` |
| Exception | `PageNotFoundException` | `ArticleNotFoundException` |
| Has parentId | Yes | No |
| Has webspace | Yes (in create) | No |
| Has type param | No | Yes (article type) |

**ArticleCreateTool example:**
```php
// Source: verified from CreateArticleMessage.php constructor
$data = array_merge($content ?? [], [
    'locale' => $locale,
    'template' => $template,
    'title' => $title,
]);

// No webspace, no parentId -- articles are flat, global entities
$message = new CreateArticleMessage($data);
$article = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
```

### Pattern 2: Traditional Manager (Tags, Categories, Media)

No message bus. Direct method calls on manager interfaces.

**TagCreateTool example:**
```php
// Source: verified from TagManagerInterface::save()
$tag = $this->tagManager->save(['name' => $name]);
return ['success' => true, 'id' => $tag->getId(), 'name' => $tag->getName()];
```

**TagListTool example:**
```php
// TagRepositoryInterface extends ObjectRepository -- provides findAll()
$tags = $this->tagRepository->findAll();
return ['tags' => array_map(fn($t) => ['id' => $t->getId(), 'name' => $t->getName()], $tags)];
```

**CategoryListTool example (tree):**
```php
// Source: verified from CategoryManagerInterface
$categories = $this->categoryManager->findChildrenByParentId(null);
$apiCategories = $this->categoryManager->getApiObjects($categories, $locale);
// Recursively build tree from API objects
```

**MediaListTool example:**
```php
// Source: verified from MediaManagerInterface::get() and MediaRepository::extractFilterVars
$filter = [];
if ($collection !== null) $filter['collection'] = $collection;
if ($types !== null) $filter['types'] = $types;
if ($search !== null) $filter['search'] = $search;

$media = $this->mediaManager->get($locale, $filter, $limit, $offset);
$total = $this->mediaManager->getCount();
```

**MediaUpdateTool example (metadata only):**
```php
// Source: verified from MediaManager::save() -- null uploadedFile + data['id'] = update
$data = ['id' => $id, 'locale' => $locale];
if ($title !== null) $data['title'] = $title;
if ($description !== null) $data['description'] = $description;
if ($copyright !== null) $data['copyright'] = $copyright;

$media = $this->mediaManager->save(null, $data, $userId);
```

### Pattern 3: Read-Only Hexagonal (Snippets)

Same as page/article GET pattern but no write operations.

```php
// SnippetRepositoryInterface mirrors PageRepositoryInterface exactly
$snippet = $this->snippetRepository->getOneBy(
    ['uuid' => $uuid, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
    [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_ADMIN => true],
);
$dimensionContent = $this->contentManager->resolve($snippet, [
    'locale' => $locale,
    'stage' => DimensionContentInterface::STAGE_DRAFT,
]);
$normalized = $this->contentManager->normalize($dimensionContent);
```

### Anti-Patterns to Avoid
- **Using message bus for tags/categories/media:** These are traditional bundles -- they do not use the hexagonal message pattern. Dispatching messages for them will fail.
- **Adding webspace to article create:** `CreateArticleMessage` has no webspace parameter. Articles are global.
- **Adding parentId to article create:** Articles are flat, not hierarchical. No parent-child relationship.
- **Accessing Doctrine directly for content resolution:** Always use `ContentManagerInterface` for pages/articles/snippets. Never query dimension content tables directly.
- **Assuming MediaManagerInterface has tag filtering:** The `get()` filter does not support tag-based filtering. Use `search` parameter or document the limitation.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Article content resolution | Custom Doctrine queries | ContentManagerInterface resolve/normalize | Handles dimension content, ghost locales, stage resolution |
| Category tree assembly | Recursive Doctrine queries | CategoryManagerInterface::findChildrenByParentId() | Handles nesting, ordering, orphan protection |
| Media format URLs | URL construction logic | MediaManagerInterface::addFormatsAndUrl() / getFormatUrls() | Handles format generation rules, CDN paths, versioning |
| Navigation tree | Custom page tree traversal | NavigationRepositoryInterface::getNavigationTree() | Handles navigation contexts, depth, segment resolution |
| Tag find-or-create | Existence check + save | TagManagerInterface::findOrCreateByName() | Atomic, handles race conditions |

## Common Pitfalls

### Pitfall 1: Article Create Without Locale
**What goes wrong:** `CreateArticleMessage` constructor asserts `$data['locale']` is a string. Missing it throws an uncaught assertion error.
**Why it happens:** The flat `$data` array hides required fields.
**How to avoid:** Always include `'locale' => $locale` in the data array before constructing the message.
**Warning signs:** `InvalidArgumentException` from `Assert::string()`.

### Pitfall 2: MediaManager::save() Semantics
**What goes wrong:** Passing `null` for `$uploadedFile` without `$data['id']` creates a broken media entity instead of updating.
**Why it happens:** `save()` routes to `modifyMedia()` only when `$data['id']` is set.
**How to avoid:** Always set `$data['id']` for metadata updates. The MediaUpdateTool must require the media ID.
**Warning signs:** New empty media record created instead of updating existing one.

### Pitfall 3: CategoryManager::save() Requires userId
**What goes wrong:** `CategoryManagerInterface::save($data, $userId, $locale)` requires a `$userId` parameter.
**Why it happens:** Traditional bundles track creator/changer, unlike message bus pattern where this is handled by middleware.
**How to avoid:** Inject `TokenStorageInterface` to get the current user ID from the security context. `$userId = $this->tokenStorage->getToken()->getUser()->getId()`.
**Warning signs:** TypeError or null user errors.

### Pitfall 4: TagManager Has No List Method
**What goes wrong:** `TagManagerInterface` only has `findById()`, `findByName()`, `save()`, `delete()`. No `findAll()` or `list()`.
**Why it happens:** Tag listing is a repository concern, not a manager concern in Sulu's architecture.
**How to avoid:** Inject `TagRepositoryInterface` for listing (inherits `findAll()` from Doctrine ObjectRepository). Use `TagManagerInterface` only for create/delete.
**Warning signs:** Trying to call non-existent `$tagManager->list()` method.

### Pitfall 5: HandleTrait messageBus Property
**What goes wrong:** Using `readonly` with HandleTrait's `$messageBus` property causes conflicts.
**Why it happens:** Symfony's HandleTrait defines `$messageBus` as a writable property. PHP traits cannot override property visibility.
**How to avoid:** Assign `$messageBus` in constructor body (not via constructor promotion). This is already established in Phase 3 -- follow the existing pattern.
**Warning signs:** Fatal error about incompatible property declaration.

### Pitfall 6: Media Filter Has No Tag Support
**What goes wrong:** Attempting to pass `tags` or `tagIds` to `MediaManagerInterface::get()` filter -- they are silently ignored.
**Why it happens:** `MediaRepository::extractFilterVars()` only extracts `collection`, `types`, `search`, `ids`, `orderBy`, `orderSort`, `systemCollections`.
**How to avoid:** Use the `search` parameter for text-based discovery. Document in tool description that tag-based media filtering is not available.
**Warning signs:** Filter seems to work but returns all media regardless of tag filter.

### Pitfall 7: Optional ContactBundle
**What goes wrong:** ContactBundle may not be installed in minimal Sulu setups. Injecting `ContactRepositoryInterface` causes container build failure.
**Why it happens:** ContactBundle is bundled with Sulu's default installation but is technically optional.
**How to avoid:** Use conditional service definition or check class existence. Consider using `#[Autoconfigure]` or a compiler pass to conditionally register the tool.
**Warning signs:** Service container compilation error for undefined service.

## Code Examples

### Article Get Tool (verified pattern)
```php
// Source: Verified from ArticleRepositoryInterface.php + existing PageGetTool.php pattern
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Article\Domain\Exception\ArticleNotFoundException;

$article = $this->articleRepository->getOneBy(
    [
        'uuid' => $uuid,
        'locale' => $locale,
        'stage' => DimensionContentInterface::STAGE_DRAFT,
    ],
    [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_ADMIN => true],
);
```

### Article Create Tool (verified pattern)
```php
// Source: Verified from CreateArticleMessage.php constructor
$data = array_merge($content ?? [], [
    'locale' => $locale,
    'template' => $template,
    'title' => $title,
]);
// type is optional -- goes in data if provided
if ($type !== null) {
    $data['type'] = $type;
}

$message = new CreateArticleMessage($data);
$article = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
```

### Category Tree Retrieval (verified pattern)
```php
// Source: Verified from CategoryManagerInterface.php
$entities = $this->categoryManager->findChildrenByParentId(null); // full tree
$apiCategories = $this->categoryManager->getApiObjects($entities, $locale);

// Build tree response
$result = [];
foreach ($apiCategories as $cat) {
    $result[] = [
        'id' => $cat->getId(),
        'name' => $cat->getName(),
        'key' => $cat->getKey(),
        'children' => $this->buildChildTree($cat->getChildren(), $locale),
    ];
}
```

### Media List with Filters (verified pattern)
```php
// Source: Verified from MediaManager.php::get() + extractFilterVars()
$filter = [];
if (null !== $collectionId) { $filter['collection'] = $collectionId; }
if (null !== $types) { $filter['types'] = $types; }
if (null !== $search) { $filter['search'] = $search; }

$mediaItems = $this->mediaManager->get($locale, $filter, $limit, $offset);
$total = $this->mediaManager->getCount();

$results = [];
foreach ($mediaItems as $media) {
    $results[] = [
        'id' => $media->getId(),
        'title' => $media->getTitle(),
        'mimeType' => $media->getMimeType(),
        'size' => $media->getSize(),
        'url' => $media->getUrl(),
    ];
}
```

### Navigation Tree Retrieval (verified pattern)
```php
// Source: Verified from NavigationRepositoryInterface.php (packages/page)
$tree = $this->navigationRepository->getNavigationTree(
    $navigationContext,  // e.g., "main", "footer"
    $locale,
    $webspaceKey,
    null,               // segment key
    $depth,             // tree depth
);
```

## Project Constraints (from CLAUDE.md)

- **Tool classes must not access Doctrine or persistence directly** -- delegate to Sulu services (managers, repositories, ContentManager)
- Run `composer fix`, `composer lint`, `composer phpstan`, `composer test` after changes
- Use `#[McpTool]` attribute with constructor injection of Sulu services
- Follow `sulu_` prefix for MCP tool names (e.g., `sulu_article_get`, `sulu_tag_create`)
- Never include AI attribution in commit messages
- Show minimal diffs only, list modified files explicitly
- Do not refactor beyond the requested scope

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 or ^11.5 |
| Config file | `phpunit.xml.dist` |
| Quick run command | `composer test` |
| Full suite command | `composer test` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ARTC-01 | Article get by UUID | unit | `composer test -- --filter ArticleGetToolTest` | Wave 0 |
| ARTC-02 | Article list with filtering | unit | `composer test -- --filter ArticleListToolTest` | Wave 0 |
| ARTC-03 | Article create via message bus | unit | `composer test -- --filter ArticleCreateToolTest` | Wave 0 |
| ARTC-04 | Article update | unit | `composer test -- --filter ArticleUpdateToolTest` | Wave 0 |
| ARTC-05 | Article delete | unit | `composer test -- --filter ArticleDeleteToolTest` | Wave 0 |
| TAXO-01 | Tag create | unit | `composer test -- --filter TagCreateToolTest` | Wave 0 |
| TAXO-02 | Tag list | unit | `composer test -- --filter TagListToolTest` | Wave 0 |
| TAXO-03 | Tag delete | unit | `composer test -- --filter TagDeleteToolTest` | Wave 0 |
| TAXO-04 | Category create | unit | `composer test -- --filter CategoryCreateToolTest` | Wave 0 |
| TAXO-05 | Category list tree | unit | `composer test -- --filter CategoryListToolTest` | Wave 0 |
| TAXO-06 | Category delete | unit | `composer test -- --filter CategoryDeleteToolTest` | Wave 0 |
| MDIA-01 | Media list/search | unit | `composer test -- --filter MediaListToolTest` | Wave 0 |
| MDIA-02 | Media get details | unit | `composer test -- --filter MediaGetToolTest` | Wave 0 |
| MDIA-03 | Media metadata update | unit | `composer test -- --filter MediaUpdateToolTest` | Wave 0 |
| READ-01 | Contact/account list | unit | `composer test -- --filter ContactListToolTest` | Wave 0 |
| READ-02 | Snippet get/list | unit | `composer test -- --filter SnippetGetToolTest` | Wave 0 |
| READ-03 | Navigation tree | unit | `composer test -- --filter NavigationGetToolTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `composer test`
- **Per wave merge:** `composer test && composer phpstan`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- All test files need creation. Follow pattern from existing `tests/Unit/Tool/PageCreateToolTest.php`.
- Tests should mock Sulu services (repositories, managers, ContentManager, MessageBus) per established pattern.

## Open Questions

1. **Article `type` field semantics**
   - What we know: CONTEXT.md D-03 specifies `type` parameter (e.g., "default", "blog"). CreateArticleMessage accepts flat data array.
   - What's unclear: Whether `type` is validated against a fixed set or is freeform. Whether it maps to a template key or is separate.
   - Recommendation: Accept `type` as optional string parameter. If it causes errors, it will surface in testing. Document in tool description.

2. **Media tag filtering limitation**
   - What we know: `MediaRepository::extractFilterVars()` does NOT extract tag-related filters. MDIA-01 requirement asks for tag filtering.
   - What's unclear: Whether there's an alternative API or if this is a genuine limitation.
   - Recommendation: Implement `search` parameter as primary discovery mechanism. Document that tag-based media filtering is not supported by the underlying API. If tag filtering is critical, consider a post-filter approach (get by collection, then filter by tag in PHP).

3. **User ID for CategoryManager::save()**
   - What we know: `save($data, $userId, $locale)` requires `$userId`.
   - What's unclear: Best way to obtain current user ID in a tool context.
   - Recommendation: Inject `TokenStorageInterface`, get user from token. If null, throw meaningful error. Same approach likely needed for MediaManager::save() which also takes `$userId`.

## Sources

### Primary (HIGH confidence)
- `vendor/sulu/sulu/packages/article/src/Application/Message/CreateArticleMessage.php` -- verified constructor takes only `array $data`
- `vendor/sulu/sulu/packages/article/src/Application/Message/ModifyArticleMessage.php` -- verified `(array $identifier, array $data)`
- `vendor/sulu/sulu/packages/article/src/Application/Message/RemoveArticleMessage.php` -- verified `(array $identifier, string $locale)`
- `vendor/sulu/sulu/packages/article/src/Application/Message/ApplyWorkflowTransitionArticleMessage.php` -- verified `(array $identifier, string $locale, string $transitionName)`
- `vendor/sulu/sulu/packages/article/src/Domain/Repository/ArticleRepositoryInterface.php` -- full filter/sort/select API
- `vendor/sulu/sulu/packages/snippet/src/Domain/Repository/SnippetRepositoryInterface.php` -- mirrors article repository
- `vendor/sulu/sulu/packages/page/src/Domain/Repository/NavigationRepositoryInterface.php` -- navigation tree API
- `vendor/sulu/sulu/src/Sulu/Bundle/TagBundle/Tag/TagManagerInterface.php` -- save/delete/find methods
- `vendor/sulu/sulu/src/Sulu/Bundle/TagBundle/Tag/TagRepositoryInterface.php` -- extends ObjectRepository for findAll()
- `vendor/sulu/sulu/src/Sulu/Bundle/CategoryBundle/Category/CategoryManagerInterface.php` -- tree retrieval, save, delete
- `vendor/sulu/sulu/src/Sulu/Bundle/MediaBundle/Media/Manager/MediaManagerInterface.php` -- get, getById, save, getCount
- `vendor/sulu/sulu/src/Sulu/Bundle/MediaBundle/Media/Manager/MediaManager.php` -- extractFilterVars implementation
- `vendor/sulu/sulu/src/Sulu/Bundle/ContactBundle/Entity/ContactRepositoryInterface.php` -- findGetAll, findById
- `vendor/sulu/sulu/src/Sulu/Bundle/ContactBundle/Entity/AccountRepositoryInterface.php` -- findById, findByIds
- Existing tool implementations: `src/Tool/Page*.php`, `src/Tool/Block*.php`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all interfaces verified from vendor source code
- Architecture: HIGH -- patterns directly derived from existing Phase 3 tools + verified Sulu APIs
- Pitfalls: HIGH -- identified through source code analysis of actual implementation details

**Research date:** 2026-03-31
**Valid until:** 2026-04-30 (stable -- Sulu 3.0 interfaces unlikely to change)
