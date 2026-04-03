# Phase 2: Context Discovery & Guidelines - Research

**Researched:** 2026-03-30
**Domain:** Sulu 3.x template/block discovery, Doctrine entity design, MCP resource patterns
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** All resources use the `sulu://` URI scheme
- **D-02:** Templates and block types are global resources with webspace filter (not webspace-scoped URIs): `sulu://templates`, `sulu://blocks` — webspace passed as query/filter parameter at read time
- **D-03:** Webspace config exposed as `sulu://webspaces` — returns all webspaces
- **D-04:** Sitemap exposed as `sulu://sitemap/{webspace}` — per-webspace
- **D-05:** Sitemap returns minimal fields only: UUID, URL, title, depth
- **D-06:** Sitemap is depth-limited — configurable, default 3 levels
- **D-07:** Guidelines stored in a Doctrine entity (`ContentGuidelines`) — not YAML config. Must be writable at runtime.
- **D-08:** Entity fields: `webspace` (nullable), `tone`, `audience`, `style`, `brand_rules`, `dos`, `don'ts` — all nullable text columns
- **D-09:** Override resolution: per-webspace guideline overrides merge with global defaults. Global row has `webspace = null`
- **D-10:** MCP write tool (`sulu_update_guidelines`) is included in Phase 2
- **D-11:** Company context stored in a separate Doctrine entity (`CompanyContext`)
- **D-12:** CompanyContext fields: `company_name`, `description`, `industry`, `website`, `key_products` — all nullable text
- **D-13:** MCP write tool (`sulu_update_company_context`) included in Phase 2
- **D-14:** Company context exposed as read-only MCP resource at `sulu://context/company`
- **D-15:** MCP Prompt for guideline generation deferred to Phase 3

### Claude's Discretion
- Exact Doctrine entity class names and table names
- Doctrine migration strategy (bundle migration vs project migration)
- Template discovery implementation (StructureMetadataFactory vs StructureFactory)
- Block type introspection approach (derive from template block properties)
- Error handling when webspace key is invalid for sitemap/resources
- Whether sitemap depth config lives in bundle config or as a per-request parameter

### Deferred Ideas (OUT OF SCOPE)
- MCP Prompt for guideline generation — Phase 3
- Admin UI for guidelines management — v2
- More general "context store" for arbitrary context entries — not pursued
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RSRC-01 | Expose available page templates with field schemas per webspace | FormMetadataProvider::getMetadata('page', $locale, ['webspace' => $key]) returns TypedFormMetadata with per-webspace filtering via WebspaceTypedFormMetadataVisitor |
| RSRC-02 | Expose available block types with field definitions per webspace | Block types are FieldMetadata items of type 'block' inside templates; iterate getForms() on TypedFormMetadata, find block fields, extract their getTypes() |
| RSRC-03 | Expose webspace configuration (locales, URLs, names) | WebspaceManagerInterface already proven in PingTool — getWebspaceCollection()->getWebspaces() gives all webspaces with locales and URLs |
| RSRC-04 | Expose sitemap/content tree per webspace and locale | NavigationRepositoryInterface::getNavigationTree() provides depth-limited tree; no separate sitemap service needed |
| GUID-01 | Store content guidelines with global defaults | ContentGuidelines Doctrine entity with nullable webspace field; null webspace = global |
| GUID-02 | Per-webspace overrides merging with global defaults | Two-row lookup: fetch global (webspace=null) then merge with per-webspace row |
| GUID-03 | Expose guidelines as MCP resource at sulu://guidelines/{webspace} | McpResourceTemplate attribute with {webspace} variable — SDK supports this via ResourceTemplateReference |
| GUID-04 | Expose company/business context as MCP resource at sulu://context/company | McpResource attribute with static URI |
</phase_requirements>

---

## Summary

Phase 2 introduces two distinct capability families: (1) read-only MCP Resources that expose CMS structure (templates, blocks, webspaces, sitemap), and (2) a writable content guidelines system with Doctrine-backed entities and corresponding MCP tools.

The Sulu template and block type discovery is powered by `FormMetadataProvider` (injected as the `form` metadata provider via `MetadataProviderRegistry`). The key call is `getMetadata('page', $locale, ['webspace' => $webspaceKey])` which returns a `TypedFormMetadata` object. Its `getForms()` method returns `FormMetadata[]` keyed by template name. Block types live as `FieldMetadata` items of type `block` inside each template — their `getTypes()` method returns `FormMetadata[]` keyed by block type name, each containing the block's field definitions.

The sitemap uses `NavigationRepositoryInterface::getNavigationTree()` — not a separate sitemap service. This provides a depth-limited tree of published pages with titles and URLs, which is exactly what D-05/D-06 specify.

**Important correction from CONTEXT.md pitfall note:** `McpResourceTemplate` IS functional in the current `mcp/sdk` v0.4. The SDK contains `ResourceTemplateReference` which compiles URI templates (like `sulu://sitemap/{webspace}`) into regex patterns and extracts variables. The "issue #9" concern in STACK.md referenced an earlier state; the implementation exists and is used by the `ReadResourceHandler`. The `symfony/mcp-bundle` registers `McpResourceTemplate` as `mcp.resource_template` tag in `McpBundle::registerMcpAttributes()`. Use it for per-webspace resources.

**Primary recommendation:** Use `FormMetadataProvider` (service ID: `sulu_admin.metadata_provider.form` or autowire as `MetadataProviderInterface $formMetadataProvider`) for template and block discovery. Use `NavigationRepositoryInterface` for sitemap. Use PHP 8 attribute-style Doctrine ORM mapping for the two new entities. Use `McpResourceTemplate` for `sulu://sitemap/{webspace}` and `sulu://guidelines/{webspace}`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | ^8.2 | Runtime | Project minimum; enables PHP 8 attribute-style ORM mapping |
| symfony/mcp-bundle | ^0.6 | MCP resource/tool attribute discovery | Already in use; `McpResource`, `McpResourceTemplate`, `McpTool` attributes |
| mcp/sdk | ^0.4 | MCP protocol primitives | Auto-required by mcp-bundle; `ResourceTemplateReference` handles URI templates |
| doctrine/orm | ^2.17 or ^3.3 | Entity persistence for guidelines/company context | Sulu dependency, already available |
| doctrine/doctrine-bundle | ^2.x | Doctrine DI integration | Sulu dependency, already available |
| sulu/sulu | ^3.0 | Sulu services (WebspaceManager, FormMetadataProvider, NavigationRepository) | Target platform |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Symfony\Component\Webspace\Manager\WebspaceManagerInterface | (bundled) | Webspace config and locale lists | RSRC-03, RSRC-04 sitemap context |
| Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface | (bundled) | Template and block type discovery | RSRC-01, RSRC-02 |
| Sulu\Page\Domain\Repository\NavigationRepositoryInterface | (bundled) | Depth-limited page tree | RSRC-04 sitemap |
| Doctrine\ORM\EntityManagerInterface | ^2.17/^3.3 | Guidelines and company context CRUD | GUID-01, GUID-02, GUID-04 |

### No Additional Packages Required
All dependencies are already present as Sulu/Symfony transitive dependencies. No `composer require` needed for Phase 2.

---

## Architecture Patterns

### Recommended Project Structure

```
src/
├── Resource/
│   ├── TemplatesResource.php          # sulu://templates — McpResource, queries FormMetadataProvider
│   ├── BlocksResource.php             # sulu://blocks — McpResource, extracts block types from templates
│   ├── WebspacesResource.php          # sulu://webspaces — McpResource, uses WebspaceManagerInterface
│   ├── SitemapResource.php            # sulu://sitemap/{webspace} — McpResourceTemplate
│   ├── GuidelinesResource.php         # sulu://guidelines/{webspace} — McpResourceTemplate
│   └── CompanyContextResource.php     # sulu://context/company — McpResource
├── Tool/
│   ├── PingTool.php                   # Existing
│   ├── UpdateGuidelinesTool.php       # sulu_update_guidelines — McpTool
│   └── UpdateCompanyContextTool.php   # sulu_update_company_context — McpTool
├── Entity/
│   ├── ContentGuidelines.php          # Doctrine entity (PHP 8 ORM attributes)
│   └── CompanyContext.php             # Doctrine entity (PHP 8 ORM attributes)
├── Repository/
│   ├── ContentGuidelinesRepository.php
│   └── CompanyContextRepository.php
└── DependencyInjection/
    └── Configuration.php              # Add sitemap.max_depth config
```

### Pattern 1: McpResource (static URI)

For static-URI resources (`sulu://webspaces`, `sulu://templates`, `sulu://blocks`, `sulu://context/company`):

```php
// Source: verified in vendor/mcp/sdk/src/Capability/Attribute/McpResource.php
use Mcp\Capability\Attribute\McpResource;

class WebspacesResource
{
    public function __construct(
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {}

    #[McpResource(
        uri: 'sulu://webspaces',
        name: 'sulu_webspaces',
        description: 'Available Sulu webspaces with locales and URLs',
        mimeType: 'application/json',
    )]
    public function getWebspaces(): array
    {
        $result = [];
        foreach ($this->webspaceManager->getWebspaceCollection()->getWebspaces() as $webspace) {
            $result[] = [
                'key' => $webspace->getKey(),
                'name' => $webspace->getName(),
                'locales' => array_map(fn($l) => $l->getLocale(), $webspace->getAllLocalizations()),
                // URLs via $webspace->getUrls() or $webspace->getEnvironments()
            ];
        }
        return $result;
    }
}
```

### Pattern 2: McpResourceTemplate (per-webspace URI)

For parameterized resources (`sulu://sitemap/{webspace}`, `sulu://guidelines/{webspace}`):

```php
// Source: verified in vendor/mcp/sdk/src/Capability/Attribute/McpResourceTemplate.php
// and vendor/mcp/sdk/src/Server/Handler/Request/ReadResourceHandler.php
use Mcp\Capability\Attribute\McpResourceTemplate;

class SitemapResource
{
    public function __construct(
        private readonly NavigationRepositoryInterface $navigationRepository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly int $maxDepth = 3,
    ) {}

    #[McpResourceTemplate(
        uriTemplate: 'sulu://sitemap/{webspace}',
        name: 'sulu_sitemap',
        description: 'Content tree for a webspace (depth-limited)',
        mimeType: 'application/json',
    )]
    public function getSitemap(string $webspace): array
    {
        // Validate webspace exists
        $ws = $this->webspaceManager->findWebspaceByKey($webspace);
        if (null === $ws) {
            throw new \InvalidArgumentException("Webspace '{$webspace}' not found");
        }

        $locale = $ws->getDefaultLocalization()?->getLocale()
            ?? $ws->getAllLocalizations()[0]->getLocale();

        // NavigationRepositoryInterface::getNavigationTree() returns tree up to $depth
        return $this->navigationRepository->getNavigationTree(
            navigationContext: 'main',   // default navigation context
            locale: $locale,
            webspaceKey: $webspace,
            segmentKey: null,
            depth: $this->maxDepth,
            properties: ['uuid', 'title', 'url', 'depth'],
        );
    }
}
```

**How the SDK extracts variables:** `ResourceTemplateReference::compileTemplate()` converts `sulu://sitemap/{webspace}` into a regex `#^sulu://sitemap/(?P<webspace>[^/]+)$#`. On `resources/read` requests, `extractVariables()` runs the regex and passes `$webspace` as a named parameter to the method. This works with PHP's named arguments injection.

### Pattern 3: Template Discovery

```php
// Source: verified in vendor/sulu/sulu/packages/page/src/Infrastructure/Sulu/Route/PageRouteDefaultsProvider.php
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

class TemplatesResource
{
    public function __construct(
        private readonly MetadataProviderInterface $formMetadataProvider, // autowire: service 'sulu_admin.metadata_provider.form'
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {}

    #[McpResource(uri: 'sulu://templates', name: 'sulu_templates', mimeType: 'application/json')]
    public function getTemplates(): array
    {
        // Webspace passed via query param — but McpResource doesn't support params.
        // DECISION D-02: webspace is a filter, return ALL templates with webspace affinity noted.
        // OR: accept all templates from 'page' key and note excluded templates per webspace.

        $locale = 'en'; // Use a sensible default locale for metadata labels
        $typedMetadata = $this->formMetadataProvider->getMetadata('page', $locale, []);

        if (!$typedMetadata instanceof TypedFormMetadata) {
            return [];
        }

        $result = [];
        foreach ($typedMetadata->getForms() as $key => $formMetadata) {
            $result[$key] = $this->normalizeTemplate($formMetadata, $locale);
        }
        return $result;
    }

    private function normalizeTemplate(FormMetadata $form, string $locale): array
    {
        $fields = [];
        foreach ($form->getItems() as $item) {
            $fields[] = [
                'name' => $item->getName(),
                'type' => $item->getType(),
                'label' => $item->getLabel($locale) ?? $item->getName(),
                'required' => $item instanceof FieldMetadata && $item->isRequired(),
            ];
        }
        return ['key' => $form->getKey(), 'fields' => $fields];
    }
}
```

### Pattern 4: Block Type Discovery

Block types in Sulu live inside templates as `FieldMetadata` items of type `block`. Each block field has `getTypes()` returning `FormMetadata[]` keyed by block type name:

```php
// Source: verified in vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/FormMetadata/FieldMetadata.php
// FieldMetadata::getTypes() returns FormMetadata[] (the block variants)
// FieldMetadata::getType() === 'block' identifies a block container

private function extractBlockTypes(TypedFormMetadata $typedMetadata, string $locale): array
{
    $blockTypes = [];

    foreach ($typedMetadata->getForms() as $templateKey => $formMetadata) {
        foreach ($formMetadata->getItems() as $item) {
            if (!$item instanceof FieldMetadata || 'block' !== $item->getType()) {
                continue;
            }
            // Each type in a block field is a named block variant
            foreach ($item->getTypes() as $blockTypeName => $blockForm) {
                if (!isset($blockTypes[$blockTypeName])) {
                    $fields = [];
                    foreach ($blockForm->getItems() as $blockField) {
                        $fields[] = [
                            'name' => $blockField->getName(),
                            'type' => $blockField->getType(),
                            'label' => $blockField->getLabel($locale) ?? $blockField->getName(),
                        ];
                    }
                    $blockTypes[$blockTypeName] = [
                        'key' => $blockTypeName,
                        'label' => $blockForm->getTitle($locale) ?? $blockTypeName,
                        'fields' => $fields,
                        'available_in_templates' => [],
                    ];
                }
                $blockTypes[$blockTypeName]['available_in_templates'][] = $templateKey;
            }
        }
    }

    return array_values($blockTypes);
}
```

### Pattern 5: Doctrine Entity (PHP 8 Attributes)

```php
// Source: Symfony 7.3 ORM attribute mapping (PHP 8 style)
// Both doctrine/orm ^2.17 and ^3.3 support PHP 8 attributes
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentGuidelinesRepository::class)]
#[ORM\Table(name: 'sulu_mcp_content_guidelines')]
#[ORM\UniqueConstraint(name: 'uniq_webspace', columns: ['webspace'])]
class ContentGuidelines
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $webspace = null;  // null = global default

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $audience = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $style = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $brandRules = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dos = null;

    #[ORM\Column(name: 'donts', type: 'text', nullable: true)]  // reserved word avoidance
    private ?string $donts = null;

    // getters/setters...
}
```

**Table naming convention:** Prefix with `sulu_mcp_` to avoid collisions with Sulu's own tables.

### Pattern 6: Guideline Override Resolution

```php
// In GuidelinesRepository or a dedicated service
public function resolveForWebspace(?string $webspaceKey): array
{
    // 1. Fetch global default
    $global = $this->findOneBy(['webspace' => null]);
    $resolved = $this->toArray($global);

    if (null === $webspaceKey) {
        return $resolved;
    }

    // 2. Merge per-webspace overrides (webspace values replace global values)
    $specific = $this->findOneBy(['webspace' => $webspaceKey]);
    if (null !== $specific) {
        foreach ($this->toArray($specific) as $field => $value) {
            if (null !== $value) {  // null means "use global default"
                $resolved[$field] = $value;
            }
        }
    }

    return $resolved;
}
```

### Pattern 7: MCP Write Tool for Guidelines

```php
use Mcp\Capability\Attribute\McpTool;

class UpdateGuidelinesTool
{
    public function __construct(
        private readonly ContentGuidelinesRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[McpTool(
        name: 'sulu_update_guidelines',
        description: 'Create or update content guidelines for a webspace (or global defaults if webspace is null)',
    )]
    public function updateGuidelines(
        ?string $webspace = null,
        ?string $tone = null,
        ?string $audience = null,
        ?string $style = null,
        ?string $brandRules = null,
        ?string $dos = null,
        ?string $donts = null,
    ): array {
        $entity = $this->repository->findOneBy(['webspace' => $webspace])
            ?? new ContentGuidelines();

        $entity->setWebspace($webspace);
        if (null !== $tone) { $entity->setTone($tone); }
        // ... other fields

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return ['success' => true, 'webspace' => $webspace ?? 'global'];
    }
}
```

### Pattern 8: Bundle Doctrine Mapping Registration

Symfony bundles must declare their entity namespaces for Doctrine autowiring. Add to `SuluMcpServerExtension::prepend()`:

```php
// In SuluMcpServerExtension::prepend()
if ($container->hasExtension('doctrine')) {
    $container->prependExtensionConfig('doctrine', [
        'orm' => [
            'mappings' => [
                'SuluMcpServerBundle' => [
                    'is_bundle' => true,
                    'type' => 'attribute',
                    'dir' => 'Entity',
                    'prefix' => 'Sulu\\McpServerBundle\\Entity',
                    'alias' => 'SuluMcpServerBundle',
                ],
            ],
        ],
    ]);
}
```

### Anti-Patterns to Avoid

- **Parsing template XML files directly:** Do not grep `config/templates/*.xml`. Use `FormMetadataProvider` — it handles caching, locale fallback, and webspace filtering via visitors.
- **Using PHPCR/DocumentManager for sitemap:** Removed in Sulu 3.0. Use `NavigationRepositoryInterface`.
- **Static URI for per-webspace resources:** D-04 specifies `sulu://sitemap/{webspace}`. Use `McpResourceTemplate`, not `McpResource`. The SDK supports it fully.
- **Custom migration command:** No separate migration runner is needed. The project running the bundle runs `doctrine:schema:update` or `doctrine:migrations:diff` (if migrations bundle is installed). Bundle registers mappings via `prepend()`.
- **Blocking the webspace parameter on McpResource:** `McpResource` has a static URI — pass webspace via the filter approach decided in D-02, or use `McpResourceTemplate` for the resources that need per-webspace URIs (D-04, GUID-03).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Template XML parsing | Custom XML parser reading `config/templates/*.xml` | `FormMetadataProvider->getMetadata('page', $locale, ['webspace' => $key])` | Provider handles caching, locale fallback, validation, XInclude blocks, webspace filtering |
| Block type discovery | Grep XML files for `<block>` elements | Iterate `FieldMetadata::getType() === 'block'` items from TypedFormMetadata | Same provider handles all template types; avoids file system coupling |
| Webspace list | Hardcoded config | `WebspaceManagerInterface::getWebspaceCollection()` | Runtime-aware, includes URL config |
| Page tree query | Custom DQL tree query | `NavigationRepositoryInterface::getNavigationTree()` | Handles Gedmo NestedTree traversal, depth limiting, navigation context filtering |
| URI template routing | Custom regex matching | `McpResourceTemplate` attribute | SDK's `ResourceTemplateReference` compiles RFC 6570 templates automatically |
| Override merging | Complex merge logic | Two-DB-lookup pattern (global + per-webspace, nulls = inherit) | Simple, predictable, testable |
| Doctrine schema | Custom `CREATE TABLE` SQL | PHP 8 ORM attributes + bundle mapping registration | Works with `doctrine:schema:update` and migrations automatically |

**Key insight:** Sulu's `FormMetadataProvider` is the authoritative runtime source for template and block type schemas. It already does caching (via `CachedFormMetadataProvider`), locale fallback, and webspace filtering. Bypassing it creates a parallel discovery mechanism that diverges from what Sulu's admin UI sees.

---

## Common Pitfalls

### Pitfall 1: Wrong FormMetadataProvider Call for Templates

**What goes wrong:** Calling `getMetadata('pages', $locale, [...])` (plural) returns null; the correct key is `'page'` (singular).

**Why it happens:** `PageInterface::RESOURCE_KEY = 'pages'` is the REST resource key. The form metadata key is `'page'` — taken from the template XML files' `<key>` element which is the template type, registered by `XmlFormMetadataLoader`. These are different concepts.

**How to avoid:** Use `'page'` as the key. Verify via `PageSmartContentProvider.php` which calls `getMetadata('page', $locale, [])`.

**Warning signs:** `MetadataNotFoundException('form', 'pages')` thrown at runtime.

---

### Pitfall 2: Missing Doctrine Mapping Registration in Bundle

**What goes wrong:** Entities are not discovered; `doctrine:schema:update` does not create the tables. `Class not found` or `Mapping exception` at boot.

**Why it happens:** Symfony bundles must explicitly declare entity namespaces to Doctrine. Unlike application code (which is auto-discovered), bundle entities require registration.

**How to avoid:** Add `prepend()` configuration in `SuluMcpServerExtension` to register the `SuluMcpServerBundle` ORM mapping as shown in Pattern 8. Alternatively, use a `DoctrineExtension::loadExtension()` call.

**Warning signs:** `Class 'Sulu\McpServerBundle\Entity\ContentGuidelines' is not a valid entity or mapped superclass` error.

---

### Pitfall 3: Reserved Word `don'ts` as PHP Property or Column Name

**What goes wrong:** `don'ts` is not a valid PHP identifier. Using `donts` as the property name but the apostrophe decision from D-08 causes confusion.

**Why it happens:** D-08 specifies `dos` and `don'ts` as field names in the entity — but PHP identifiers cannot contain apostrophes.

**How to avoid:** Name the PHP property `$donts`. In the Doctrine column mapping, use `name: 'donts'`. Expose as `"don'ts"` only in the MCP resource output (in the serialized array). Document this discrepancy.

---

### Pitfall 4: NavigationRepository Requires Navigation Context

**What goes wrong:** `NavigationRepositoryInterface::getNavigationTree()` requires a `navigationContext` string. Passing an invalid context (or the default 'main' when a webspace has no 'main' navigation) returns an empty array silently.

**Why it happens:** Sulu navigation contexts are configured per-webspace. Not all webspaces define a 'main' navigation. The repository does not throw for invalid contexts.

**How to avoid:** Look up the webspace's configured navigation contexts before calling `getNavigationTree()`. If 'main' doesn't exist, use the first configured context. Or: fall back to `getNavigationFlat()` with depth limit. Document that an empty result means "no navigation configured" not "no pages exist."

**Warning signs:** Sitemap resource returns `[]` even though pages exist in Sulu admin.

---

### Pitfall 5: Webspace URL Exposure in sulu://webspaces

**What goes wrong:** `$webspace->getUrls()` returns URLs including environment-specific variants (dev, staging, prod). Exposing all URLs may confuse AI clients or expose internal URLs.

**Why it happens:** Sulu webspaces can have multiple environments each with different URLs.

**How to avoid:** Filter to `main` environment URLs only. Use `$webspace->getMainUrl()` if available, or filter `getUrls()` by `$url->getEnvironment() === 'prod'`. Return the primary URL per locale.

---

### Pitfall 6: McpResourceTemplate Variable Injection via Named Parameters

**What goes wrong:** The `ReadResourceHandler` passes extracted template variables to the method using `$arguments` array merge. PHP's named argument injection from arrays depends on PHP 8's named argument support in `call_user_func_array`.

**Why it happens:** `ResourceTemplateReference::extractVariables()` builds `['webspace' => 'sulu-io']`. The `ReferenceHandlerInterface::handle()` must invoke the method with these named arguments.

**How to avoid:** Name the PHP method parameter exactly matching the URI template variable name: `{webspace}` → `string $webspace`. The SDK's `ResourceTemplateReference::getVariableNames()` captures the template variable names for this injection.

**Warning signs:** Method receives null for `$webspace` even though the URI contains a webspace value.

---

### Pitfall 7: Guidelines Guideline Token Size

**What goes wrong:** Unconstrained text fields allow guidelines to grow very large. AI context windows that include large guidelines perform worse.

**Why it happens:** D-08 specifies "all nullable text columns" with no character limit. Free text is easy to over-populate.

**How to avoid:** Add a practical per-field character limit at the write tool layer (not the entity layer). Warn if total guideline text exceeds 2000 characters. Document recommended lengths (tone: 200 chars, audience: 300 chars, etc.). This is a soft enforcement at the tool, not a hard database constraint.

---

## Code Examples

### Resolving the FormMetadataProvider Service

```php
// In services.yaml — autowire by interface with service locator alias
Sulu\McpServerBundle\Resource\TemplatesResource:
    arguments:
        $formMetadataProvider: '@sulu_admin.metadata_provider.form'

// Or: inject MetadataProviderRegistry and call getMetadataProvider('form')
// Source: verified in vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/MetadataProviderRegistry.php
```

The service ID for the form metadata provider in Sulu 3.0 is `sulu_admin.metadata_provider.form` (the `CachedFormMetadataProvider` decorator). Inject `MetadataProviderInterface` — autowire by the service ID since multiple providers exist and Symfony cannot auto-select the right one.

### WebspaceTypedFormMetadataVisitor — Webspace Filtering

```php
// Source: verified in vendor/sulu/sulu/packages/page/src/Infrastructure/Sulu/Admin/MetadataVisitor/WebspaceTypedFormMetadataVisitor.php
// When metadataOptions includes 'webspace', the visitor removes excluded templates
// and sets the default template for that webspace.

$typedMetadata = $formMetadataProvider->getMetadata('page', $locale, [
    'webspace' => $webspaceKey,  // triggers WebspaceTypedFormMetadataVisitor
]);
// $typedMetadata->getForms() now only contains templates valid for $webspaceKey
// $typedMetadata->getDefaultType() returns the default template for $webspaceKey
```

### ContentGuidelines Entity (Full)

```php
declare(strict_types=1);

namespace Sulu\McpServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\McpServerBundle\Repository\ContentGuidelinesRepository;

#[ORM\Entity(repositoryClass: ContentGuidelinesRepository::class)]
#[ORM\Table(name: 'sulu_mcp_content_guidelines')]
#[ORM\UniqueConstraint(name: 'uniq_guidelines_webspace', columns: ['webspace'])]
class ContentGuidelines
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Null = global default row */
    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $webspace = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $audience = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $style = null;

    #[ORM\Column(name: 'brand_rules', type: 'text', nullable: true)]
    private ?string $brandRules = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dos = null;

    #[ORM\Column(name: 'donts', type: 'text', nullable: true)]
    private ?string $donts = null;

    // Full getters and setters for all fields
}
```

### CompanyContext Entity (Full)

```php
declare(strict_types=1);

namespace Sulu\McpServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\McpServerBundle\Repository\CompanyContextRepository;

#[ORM\Entity(repositoryClass: CompanyContextRepository::class)]
#[ORM\Table(name: 'sulu_mcp_company_context')]
class CompanyContext
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'company_name', type: 'string', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(name: 'key_products', type: 'text', nullable: true)]
    private ?string $keyProducts = null;

    // Full getters and setters
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PHPCR/Jackalope for page tree | NavigationRepositoryInterface (Doctrine, Gedmo NestedSet) | Sulu 3.0 | DocumentManager is gone; all tree queries via NavigationRepository |
| XML annotation Doctrine mapping | PHP 8 attribute ORM mapping | Doctrine ORM ^2.13+ | Cleaner; no separate XML files for bundle entities |
| McpResourceTemplate "issue #9" (claimed broken) | McpResourceTemplate fully functional via ResourceTemplateReference | mcp/sdk v0.4 | Use McpResourceTemplate for parameterized URIs |
| `StructureMetadataFactory` for template discovery | `FormMetadataProvider` (MetadataProviderRegistry 'form') | Sulu 3.0 | New metadata system with caching and visitor pattern |

**Deprecated/outdated:**
- `Sulu\Component\Content\Metadata\Factory\StructureMetadataFactory`: PHPCR-era class. Gone in Sulu 3.0. Do not use.
- `DocumentManager` for page trees: Removed entirely. Use `NavigationRepositoryInterface`.
- XML-based Doctrine mapping files (`.orm.xml`) for new bundle entities: Works but PHP 8 attribute style is preferred for new code.

---

## Open Questions

1. **NavigationRepository navigation context name**
   - What we know: `getNavigationTree()` requires a `navigationContext` string; 'main' is convention
   - What's unclear: Does every Sulu 3.0 webspace define a 'main' navigation context? How to discover valid contexts per webspace?
   - Recommendation: Default to 'main'; catch empty results and document as "no navigation configured." In code, consider exposing navigation contexts from webspace config as a fallback.

2. **FormMetadataProvider service ID**
   - What we know: Service exists as `sulu_admin.metadata_provider.form` (inferred from Sulu naming conventions); the `CachedFormMetadataProvider` decorates it
   - What's unclear: Exact autowire alias in a vanilla Sulu 3.0 project — could be `MetadataProviderInterface $formMetadataProvider` with the right type-hint if an alias is set
   - Recommendation: Inject by explicit service ID `@sulu_admin.metadata_provider.form` in `services.yaml` to be safe. Verify in the executing project's container dump.

3. **Doctrine Migrations vs Schema Update**
   - What we know: `doctrine/migrations` bundle is not in the bundle's `composer.json`; the host project may or may not have it
   - What's unclear: Whether the bundle should ship with a migration or rely on `doctrine:schema:update`
   - Recommendation: Ship with `doctrine:schema:update --force` instructions for development. Document that production deployments should use migrations if the host project has the migrations bundle. Do not add `doctrine/migrations-bundle` as a bundle dependency.

---

## Environment Availability

No external runtime dependencies beyond already-installed Sulu/Symfony packages.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| doctrine/orm | Entity persistence | Yes (Sulu dep) | ^2.17 or ^3.3 | — |
| doctrine/doctrine-bundle | DI registration | Yes (Sulu dep) | ^2.x | — |
| NavigationRepositoryInterface | RSRC-04 sitemap | Yes (Sulu 3.0 package) | Sulu 3.0 | — |
| FormMetadataProvider | RSRC-01, RSRC-02 | Yes (Sulu AdminBundle) | Sulu 3.0 | — |
| WebspaceManagerInterface | RSRC-03 | Yes (proven in PingTool) | Sulu 3.0 | — |
| mcp/sdk McpResourceTemplate | GUID-03, RSRC-04 | Yes (in vendor) | ^0.4 | — |

**No missing dependencies.** All Phase 2 services are available in a standard Sulu 3.0 + symfony/mcp-bundle project.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.3 |
| Config file | `phpunit.xml.dist` |
| Quick run command | `composer test -- --filter=<TestClass>` |
| Full suite command | `composer test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RSRC-01 | TemplatesResource returns TypedFormMetadata-derived array | unit | `composer test -- --filter=TemplatesResourceTest` | Wave 0 |
| RSRC-02 | BlocksResource extracts block types from template metadata | unit | `composer test -- --filter=BlocksResourceTest` | Wave 0 |
| RSRC-03 | WebspacesResource returns webspace array with locales | unit | `composer test -- --filter=WebspacesResourceTest` | Wave 0 |
| RSRC-04 | SitemapResource calls NavigationRepository with correct params | unit | `composer test -- --filter=SitemapResourceTest` | Wave 0 |
| GUID-01 | ContentGuidelines entity persists all fields correctly | unit | `composer test -- --filter=ContentGuidelinesTest` | Wave 0 |
| GUID-02 | Override resolution merges global + webspace, nulls inherit | unit | `composer test -- --filter=ContentGuidelinesRepositoryTest` | Wave 0 |
| GUID-03 | GuidelinesResource URI template extracts webspace variable | unit | `composer test -- --filter=GuidelinesResourceTest` | Wave 0 |
| GUID-04 | CompanyContextResource returns structured array | unit | `composer test -- --filter=CompanyContextResourceTest` | Wave 0 |
| GUID-01+D-10 | UpdateGuidelinesTool persists entity and returns success | unit | `composer test -- --filter=UpdateGuidelinesToolTest` | Wave 0 |
| GUID-04+D-13 | UpdateCompanyContextTool persists entity | unit | `composer test -- --filter=UpdateCompanyContextToolTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `composer test -- --filter=<affected test class>`
- **Per wave merge:** `composer test` (full suite)
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
All test files are new — none exist yet:
- [ ] `tests/Unit/Resource/TemplatesResourceTest.php`
- [ ] `tests/Unit/Resource/BlocksResourceTest.php`
- [ ] `tests/Unit/Resource/WebspacesResourceTest.php`
- [ ] `tests/Unit/Resource/SitemapResourceTest.php`
- [ ] `tests/Unit/Resource/GuidelinesResourceTest.php`
- [ ] `tests/Unit/Resource/CompanyContextResourceTest.php`
- [ ] `tests/Unit/Tool/UpdateGuidelinesToolTest.php`
- [ ] `tests/Unit/Tool/UpdateCompanyContextToolTest.php`
- [ ] `tests/Unit/Entity/ContentGuidelinesTest.php`
- [ ] `tests/Unit/Repository/ContentGuidelinesRepositoryTest.php`

**Existing pattern to follow:** `tests/Unit/Tool/PingToolTest.php` — constructor injection with PHPUnit mocks, no Symfony kernel required, pure unit tests.

---

## Project Constraints (from CLAUDE.md)

- **PHP 8.2+**: Use `declare(strict_types=1)` in all files; PHP 8 attribute syntax for ORM and MCP
- **Namespace**: `Sulu\McpServerBundle\{subdomain}` (e.g., `Sulu\McpServerBundle\Resource\`, `Sulu\McpServerBundle\Entity\`)
- **No separate auth system**: Operations use Sulu's SecurityBundle (not relevant for read resources, but write tools must respect authenticated user context)
- **No external runtime dependencies**: All Phase 2 code uses only Sulu/Doctrine/Symfony services already available
- **No PHPCR/Jackalope**: Do not reference DocumentManager, PageDocument, or PHPCR classes
- **Transport**: Streamable HTTP only (already established in Phase 1)
- **GSD workflow**: All changes go through GSD execution flow

---

## Sources

### Primary (HIGH confidence)
- `vendor/mcp/sdk/src/Capability/Attribute/McpResource.php` — attribute parameters and semantics
- `vendor/mcp/sdk/src/Capability/Attribute/McpResourceTemplate.php` — URI template attribute
- `vendor/mcp/sdk/src/Capability/Registry/ResourceTemplateReference.php` — URI template compilation and variable extraction
- `vendor/mcp/sdk/src/Server/Handler/Request/ReadResourceHandler.php` — how resources/read dispatches to handlers
- `vendor/symfony/mcp-bundle/src/McpBundle.php` — attribute registration, `mcp.resource_template` tag
- `vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/FormMetadata/FormMetadataProvider.php` — template metadata provider
- `vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/FormMetadata/TypedFormMetadata.php` — getForms() container
- `vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/FormMetadata/FieldMetadata.php` — block field getTypes()
- `vendor/sulu/sulu/packages/page/src/Infrastructure/Sulu/Admin/MetadataVisitor/WebspaceTypedFormMetadataVisitor.php` — webspace-aware template filtering
- `vendor/sulu/sulu/packages/page/src/Domain/Repository/NavigationRepositoryInterface.php` — sitemap/tree interface
- `vendor/sulu/sulu/packages/page/src/Infrastructure/Sulu/Route/PageRouteDefaultsProvider.php` — example of correct 'page' key usage with TypedFormMetadata
- `src/Tool/PingTool.php` — established tool pattern (McpTool attribute, constructor injection, return array)
- `src/DependencyInjection/SuluMcpServerExtension.php` — how to add prepend() config

### Secondary (MEDIUM confidence)
- `vendor/sulu/sulu/packages/page/src/Domain/Model/PageInterface.php` — `RESOURCE_KEY = 'pages'` (distinct from the form metadata key `'page'`)
- `vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/MetadataProviderRegistry.php` — how to access form metadata provider
- `vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Metadata/FormMetadata/CachedFormMetadataProvider.php` — caching layer decorating FormMetadataProvider

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all packages verified in vendor directory
- Architecture: HIGH — `McpResourceTemplate` functionality verified in SDK source; Sulu services verified in packages
- Pitfalls: HIGH — based on source code inspection of actual APIs

**Research date:** 2026-03-30
**Valid until:** 2026-04-30 (stable Sulu 3.x APIs; mcp/sdk pre-1.0 so check for API changes after any dependency update)
