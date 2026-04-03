---
phase: 02-context-discovery-guidelines
verified: 2026-03-30T15:30:00Z
status: passed
score: 13/13 must-haves verified
gaps: []
human_verification:
  - test: "Verify sulu://sitemap/{webspace} returns a real navigation tree in a running Sulu project"
    expected: "List of pages with uuid, title, url fields at correct depth"
    why_human: "NavigationRepositoryInterface.getNavigationTree() requires a live Sulu database with content; cannot verify output shape without a running CMS"
  - test: "Verify sulu_update_guidelines persists to DB and sulu://guidelines/{webspace} returns merged result"
    expected: "After calling sulu_update_guidelines with tone='friendly', reading sulu://guidelines/global returns tone='friendly'"
    why_human: "Requires live Doctrine database connection to verify EntityManager.persist()+flush() and subsequent repository read"
  - test: "Verify sulu_update_company_context singleton upsert behaviour"
    expected: "Calling sulu_update_company_context twice updates the same row, not creating a second row"
    why_human: "Singleton pattern correctness requires a live database to observe row counts"
---

# Phase 2: Context Discovery & Guidelines Verification Report

**Phase Goal:** Expose Sulu CMS structure and content guidelines to AI clients via MCP Resources and Tools
**Verified:** 2026-03-30T15:30:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | MCP client can read sulu://templates and receive a list of template keys each with field definitions | VERIFIED | `TemplatesResource::getTemplates()` with `#[McpResource(uri: 'sulu://templates')]`, delegates to `FormMetadataProvider`, iterates `TypedFormMetadata::getForms()`, returns keyed array with `fields` |
| 2 | MCP client can read sulu://blocks and receive a deduplicated list of block type keys with field definitions and available_in_templates | VERIFIED | `BlocksResource::getBlocks()` with `#[McpResource(uri: 'sulu://blocks')]`, `extractBlockTypes()` uses `isset($blockTypes[$blockTypeName])` guard for deduplication, builds `available_in_templates` array per block type |
| 3 | MCP client can read sulu://webspaces and receive all webspaces with key, name, locales, primary URL | VERIFIED | `WebspacesResource::getWebspaces()` with `#[McpResource(uri: 'sulu://webspaces')]`, traverses `Portal::getEnvironment('prod')` for URL lookup, returns `['key', 'name', 'locales', 'url']` per webspace |
| 4 | MCP client can read sulu://sitemap/{webspace} and receive a depth-limited page tree | VERIFIED | `SitemapResource::getSitemap(string $webspace)` with `#[McpResourceTemplate(uriTemplate: 'sulu://sitemap/{webspace}')]`, calls `NavigationRepositoryInterface::getNavigationTree()` with `depth: $this->maxDepth` |
| 5 | Invalid webspace key in sulu://sitemap/{webspace} returns a structured error, not a 500 | VERIFIED | `SitemapResource::getSitemap()` checks `$this->webspaceManager->findWebspaceByKey($webspace)` and throws `\InvalidArgumentException` with descriptive message when null |
| 6 | Sitemap max_depth is configurable via sulu_mcp_server.sitemap.max_depth bundle config (default: 3) | VERIFIED | `Configuration.php` has `arrayNode('sitemap')->integerNode('max_depth')->defaultValue(3)->min(1)->max(10)`; `SuluMcpServerExtension::load()` sets `sulu_mcp_server.sitemap.max_depth`; `SitemapResource` wired with `$maxDepth: '%sulu_mcp_server.sitemap.max_depth%'` in services.yaml |
| 7 | MCP client can read sulu://guidelines/{webspace} and receive merged guidelines (global defaults + webspace-specific overrides) | VERIFIED | `GuidelinesResource::getGuidelines(string $webspace)` with `#[McpResourceTemplate(uriTemplate: 'sulu://guidelines/{webspace}')]`, delegates to `ContentGuidelinesRepository::resolveForWebspace()` which implements the fetch-global/merge-specific algorithm |
| 8 | MCP client can read sulu://guidelines/global and receive global defaults | VERIFIED | `resolveForWebspace()` has explicit `if ('global' === $webspaceKey) { return $resolved; }` guard that returns unmerged global defaults |
| 9 | MCP client can call sulu_update_guidelines tool and a ContentGuidelines row is persisted or updated | VERIFIED | `UpdateGuidelinesTool::updateGuidelines()` with `#[McpTool(name: 'sulu_update_guidelines')]`, uses `findOneBy(['webspace' => $webspaceKey]) ?? new ContentGuidelines()` upsert pattern, calls `entityManager->persist($entity)` and `entityManager->flush()` |
| 10 | MCP client can read sulu://context/company and receive company name, description, industry, website, key_products | VERIFIED | `CompanyContextResource::getCompanyContext()` with `#[McpResource(uri: 'sulu://context/company', name: 'sulu_company_context')]`, calls `findOneBy([])` and `repository->toArray()` which returns all five fields |
| 11 | MCP client can call sulu_update_company_context tool and a CompanyContext row is persisted or updated | VERIFIED | `UpdateCompanyContextTool::updateCompanyContext()` with `#[McpTool(name: 'sulu_update_company_context')]`, singleton upsert via `findOneBy([]) ?? new CompanyContext()`, `entityManager->persist()+flush()` |
| 12 | Per-webspace guidelines with null fields inherit the global default values for those fields | VERIFIED | `resolveForWebspace()` merges with `if (null !== $value && 'webspace' !== $field)` — only non-null webspace field values replace global values |
| 13 | doctrine:schema:update creates sulu_mcp_content_guidelines and sulu_mcp_company_context tables | VERIFIED | `ContentGuidelines` has `#[ORM\Table(name: 'sulu_mcp_content_guidelines')]`; `CompanyContext` has `#[ORM\Table(name: 'sulu_mcp_company_context')]`; Doctrine ORM mapping registered in `SuluMcpServerExtension::prepend()` under `SuluMcpServerBundle` namespace |

**Score:** 13/13 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `src/Resource/TemplatesResource.php` | sulu://templates McpResource | VERIFIED | `#[McpResource(uri: 'sulu://templates', name: 'sulu_templates')]`, 57 lines, non-trivial implementation |
| `src/Resource/BlocksResource.php` | sulu://blocks McpResource | VERIFIED | `#[McpResource(uri: 'sulu://blocks', name: 'sulu_blocks')]`, deduplication algorithm present |
| `src/Resource/WebspacesResource.php` | sulu://webspaces McpResource | VERIFIED | `#[McpResource(uri: 'sulu://webspaces', name: 'sulu_webspaces')]`, Portal/Environment URL traversal |
| `src/Resource/SitemapResource.php` | sulu://sitemap/{webspace} McpResourceTemplate | VERIFIED | `#[McpResourceTemplate(uriTemplate: 'sulu://sitemap/{webspace}')]`, `getSitemap(string $webspace)` parameter matches |
| `src/Entity/ContentGuidelines.php` | Doctrine entity sulu_mcp_content_guidelines | VERIFIED | `#[ORM\Table(name: 'sulu_mcp_content_guidelines')]`, 7 mapped columns, all nullable, `$donts` PHP property |
| `src/Entity/CompanyContext.php` | Doctrine entity sulu_mcp_company_context | VERIFIED | `#[ORM\Table(name: 'sulu_mcp_company_context')]`, 5 mapped columns, all nullable |
| `src/Repository/ContentGuidelinesRepository.php` | resolveForWebspace() merge logic | VERIFIED | `resolveForWebspace(string $webspaceKey)` with global fetch + per-webspace merge; `"don'ts"` key in `toArray()` output |
| `src/Repository/CompanyContextRepository.php` | toArray() serialization | VERIFIED | Public `toArray(?CompanyContext $entity)` returning all 5 fields as snake_case keys |
| `src/Resource/GuidelinesResource.php` | sulu://guidelines/{webspace} McpResourceTemplate | VERIFIED | `#[McpResourceTemplate(uriTemplate: 'sulu://guidelines/{webspace}', name: 'sulu_guidelines')]` |
| `src/Resource/CompanyContextResource.php` | sulu://context/company McpResource | VERIFIED | `#[McpResource(uri: 'sulu://context/company', name: 'sulu_company_context')]` |
| `src/Tool/UpdateGuidelinesTool.php` | sulu_update_guidelines MCP tool | VERIFIED | `#[McpTool(name: 'sulu_update_guidelines')]`, upsert pattern, soft 2000-char warning |
| `src/Tool/UpdateCompanyContextTool.php` | sulu_update_company_context MCP tool | VERIFIED | `#[McpTool(name: 'sulu_update_company_context')]`, singleton upsert |
| `tests/Unit/Resource/TemplateResourceTest.php` | Unit tests for RSRC-01 | VERIFIED | 4 test methods, all pass |
| `tests/Unit/Resource/BlockTypeResourceTest.php` | Unit tests for RSRC-02 | VERIFIED | 4 test methods, all pass |
| `tests/Unit/Resource/WebspaceResourceTest.php` | Unit tests for RSRC-03 | VERIFIED | 3 test methods, all pass |
| `tests/Unit/Resource/SitemapResourceTest.php` | Unit tests for RSRC-04 | VERIFIED | 5 test methods, all pass |
| `tests/Unit/Entity/ContentGuidelinesEntityTest.php` | Unit tests for GUID entity | VERIFIED | 4 test methods, all pass |
| `tests/Unit/Entity/CompanyContextEntityTest.php` | Unit tests for CompanyContext entity | VERIFIED | 3 test methods, all pass |
| `tests/Unit/Resource/ContentGuidelinesResourceTest.php` | Unit tests for GUID-01/02/03 | VERIFIED | 4 test methods, all pass |
| `tests/Unit/Resource/CompanyContextResourceTest.php` | Unit tests for GUID-04 | VERIFIED | 3 test methods, all pass |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `TemplatesResource.php` | `FormMetadataProvider` | `MetadataProviderInterface` constructor injection | WIRED | Explicit alias `$formMetadataProvider: '@sulu_admin.metadata_provider.form'` in services.yaml |
| `BlocksResource.php` | `FormMetadataProvider` | `MetadataProviderInterface` constructor injection | WIRED | Explicit alias `$formMetadataProvider: '@sulu_admin.metadata_provider.form'` in services.yaml |
| `SitemapResource.php` | `NavigationRepositoryInterface` | Constructor injection | WIRED | `private readonly NavigationRepositoryInterface $navigationRepository` in constructor |
| `SuluMcpServerExtension.php` | Doctrine ORM mappings | `prependExtensionConfig('doctrine', ...)` in `prepend()` | WIRED | `if ($container->hasExtension('doctrine'))` block registers `SuluMcpServerBundle` mapping |
| `GuidelinesResource.php` | `ContentGuidelinesRepository` | `resolveForWebspace()` call | WIRED | `return $this->repository->resolveForWebspace($webspace)` — direct delegation, no interposing logic |
| `UpdateGuidelinesTool.php` | `ContentGuidelines` entity | `EntityManagerInterface::persist()+flush()` | WIRED | `$this->entityManager->persist($entity); $this->entityManager->flush()` present |
| `SitemapResource.php` | `sulu_mcp_server.sitemap.max_depth` param | `$maxDepth` constructor arg from container | WIRED | `$maxDepth: '%sulu_mcp_server.sitemap.max_depth%'` in services.yaml |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| `TemplatesResource.php` | `$typedMetadata` | `$this->formMetadataProvider->getMetadata('page', 'en', [])` | Yes — delegates to Sulu's real FormMetadataProvider which reads template XML/annotations | FLOWING |
| `BlocksResource.php` | Block types from `$typedMetadata` | Same `FormMetadataProvider` as above | Yes | FLOWING |
| `WebspacesResource.php` | `$webspace` collection | `$this->webspaceManager->getWebspaceCollection()->getWebspaces()` | Yes — WebspaceManagerInterface reads webspace configuration files at kernel boot | FLOWING |
| `SitemapResource.php` | Navigation tree | `$this->navigationRepository->getNavigationTree(...)` | Yes — NavigationRepositoryInterface queries Sulu's content store (SEAL/Doctrine) | FLOWING |
| `GuidelinesResource.php` | `$resolved` array | `$this->repository->resolveForWebspace($webspace)` | Yes — `findOneBy()` queries `sulu_mcp_content_guidelines` table via Doctrine | FLOWING |
| `CompanyContextResource.php` | `$entity` | `$this->repository->findOneBy([])` | Yes — queries `sulu_mcp_company_context` table via Doctrine | FLOWING |
| `UpdateGuidelinesTool.php` | Persisted `ContentGuidelines` | `EntityManagerInterface::persist()+flush()` | Yes — writes to DB, not in-memory only | FLOWING |
| `UpdateCompanyContextTool.php` | Persisted `CompanyContext` | `EntityManagerInterface::persist()+flush()` | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All 47 unit tests pass | `composer test` | OK (47 tests, 152 assertions) | PASS |
| PHP lint clean — all phase 02 production files | `php -l` on 12 files | No syntax errors detected | PASS |
| No TODO/FIXME/placeholder comments in src/ | grep scan | No matches | PASS |
| Commits for both plans exist | `git log --oneline` | `ecce527` (feat 02-01), `b0273d8` (feat 02-02) | PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| RSRC-01 | 02-01 | Expose available page templates with field schemas per webspace | SATISFIED | `TemplatesResource` with `sulu://templates`, iterates `TypedFormMetadata::getForms()` returning per-key field arrays |
| RSRC-02 | 02-01 | Expose available block types with field definitions per webspace | SATISFIED | `BlocksResource` with `sulu://blocks`, deduplication guard + `available_in_templates` accumulation |
| RSRC-03 | 02-01 | Expose webspace configuration (locales, URLs, names) | SATISFIED | `WebspacesResource` with `sulu://webspaces`, Portal/Environment URL traversal for prod URL |
| RSRC-04 | 02-01 | Expose sitemap/content tree per webspace and locale | SATISFIED | `SitemapResource` with `sulu://sitemap/{webspace}`, `NavigationRepositoryInterface::getNavigationTree()` with configurable depth |
| GUID-01 | 02-02 | Store content guidelines (tone, audience, style, brand rules) with global defaults | SATISFIED | `ContentGuidelines` entity (table: `sulu_mcp_content_guidelines`) with nullable tone/audience/style/brand_rules/dos/donts columns; `UpdateGuidelinesTool` for persistence |
| GUID-02 | 02-02 | Support per-webspace guideline overrides that merge with global defaults | SATISFIED | `ContentGuidelinesRepository::resolveForWebspace()` implements fetch-global + merge-non-null-specific algorithm |
| GUID-03 | 02-02 | Expose guidelines as MCP resource at `sulu://guidelines/{webspace}` | SATISFIED | `GuidelinesResource` with `#[McpResourceTemplate(uriTemplate: 'sulu://guidelines/{webspace}')]` |
| GUID-04 | 02-02 | Expose company/business context as MCP resource at `sulu://context/company` | SATISFIED | `CompanyContextResource` with `#[McpResource(uri: 'sulu://context/company')]`; `UpdateCompanyContextTool` for persistence |

**Note:** REQUIREMENTS.md still marks GUID-01 through GUID-04 as "Pending" (lines 80-83) and "Pending" in the tracking table (lines 146-149). The implementations are complete and verified in code. The REQUIREMENTS.md file has not been updated to reflect completion — this is a documentation-only discrepancy, not an implementation gap.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `src/Entity/ContentGuidelines.php` | 18 | PHPStan: `$id` property type `int\|null` — `int` is assigned only by Doctrine internals, PHPStan flags it as unused type | Warning | No runtime impact; standard Doctrine ORM pattern; Doctrine assigns `int` after `persist()+flush()` via reflection |
| `src/Entity/CompanyContext.php` | 17 | Same PHPStan warning on `$id` property | Warning | No runtime impact; identical cause |

Both PHPStan warnings are standard false positives for Doctrine entity ID fields. Doctrine assigns the integer value through reflection after a `flush()`, which PHPStan cannot trace. The `?int` type is semantically correct — the field is `null` before persistence and `int` after. These are pre-existing warnings that do not affect correctness.

### Human Verification Required

#### 1. Sitemap navigation tree shape

**Test:** In a running Sulu project with content, call `sulu://sitemap/{webspace}` with a valid webspace key
**Expected:** Returns array of page nodes, each with `uuid`, `title`, `url` keys, limited to `max_depth` levels of nesting
**Why human:** `NavigationRepositoryInterface::getNavigationTree()` requires a live Sulu database with pages; the unit test mocks the repository so shape cannot be verified programmatically

#### 2. Guidelines persistence and merge round-trip

**Test:** Call `sulu_update_guidelines` with `webspace=null, tone="friendly"`, then call `sulu_update_guidelines` with `webspace="my-site", audience="developers"`, then read `sulu://guidelines/my-site`
**Expected:** Returns `{tone: "friendly", audience: "developers", ...}` — global tone merged with per-webspace audience
**Why human:** Requires live Doctrine database to verify the EntityManager writes and repository reads produce the correct merged output

#### 3. Company context singleton upsert

**Test:** Call `sulu_update_company_context` twice with different company names
**Expected:** Only one row in `sulu_mcp_company_context` table, with the second call's values
**Why human:** Singleton pattern verification (`findOneBy([])` returning and updating the first row) requires a live database to observe row count

### Gaps Summary

No gaps found. All 13 truths verified. All 20 artifacts exist and are substantive (non-stub implementations with real logic). All key links are wired. All 8 requirements satisfied. The full unit test suite (47 tests, 152 assertions) passes. Three items are routed to human verification because they require a live Sulu database.

---

_Verified: 2026-03-30T15:30:00Z_
_Verifier: Claude (gsd-verifier)_
