# Architecture Patterns

**Domain:** Symfony bundle MCP server for Sulu CMS 3.x
**Researched:** 2026-03-29

## Recommended Architecture

The Sulu MCP Server is a Symfony bundle that layers MCP protocol handling on top of Sulu's existing service infrastructure. The architecture follows a four-layer design with clear boundaries: Transport, MCP Protocol, Application Service, and Sulu Integration.

```
+------------------------------------------------------------------+
|  AI Client (Claude.ai, ChatGPT via MCP gateway, Claude Code)    |
+------------------------------------------------------------------+
         |  HTTP POST / SSE (Streamable HTTP transport)
         |  Authorization: Bearer <token>
         |  Mcp-Session-Id: <session-id>
         v
+------------------------------------------------------------------+
|  LAYER 1: Transport & Auth (Symfony HTTP Kernel)                 |
|  +------------------------------------------------------------+  |
|  | Symfony Firewall (sulu_mcp)                                |  |
|  | Custom Authenticator: resolves Bearer token -> Sulu User   |  |
|  +------------------------------------------------------------+  |
|  | Streamable HTTP Controller                                 |  |
|  | - POST /_mcp  (JSON-RPC requests)                          |  |
|  | - GET  /_mcp  (SSE stream for server-initiated messages)   |  |
|  | - DELETE /_mcp (session termination)                       |  |
|  | Session management (file/cache-based)                      |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
         |  JSON-RPC 2.0 messages
         v
+------------------------------------------------------------------+
|  LAYER 2: MCP Protocol (symfony/mcp-sdk + symfony/mcp-bundle)    |
|  +------------------------------------------------------------+  |
|  | MCP Server (capability negotiation, lifecycle)             |  |
|  | Tool Registry   (auto-discovered via #[McpTool])           |  |
|  | Resource Registry (auto-discovered via #[McpResource])     |  |
|  | Prompt Registry  (auto-discovered via #[McpPrompt])        |  |
|  | Resource Template Registry (#[McpResourceTemplate])        |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
         |  PHP method calls
         v
+------------------------------------------------------------------+
|  LAYER 3: Application Services (this bundle's core logic)        |
|  +------------------------------------------------------------+  |
|  | MCP Tool Handlers (pages, articles, blocks, media, etc.)   |  |
|  | MCP Resource Providers (templates, blocks, sitemap, etc.)  |  |
|  | MCP Prompt Generators (content guidelines, instructions)   |  |
|  | Content Guidelines Service                                 |  |
|  | Block Type Discovery Service                               |  |
|  | Permission Guard (wraps Sulu SecurityChecker)              |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
         |  Sulu service calls (DI-injected)
         v
+------------------------------------------------------------------+
|  LAYER 4: Sulu CMS 3.x Services (existing, not modified)        |
|  +------------------------------------------------------------+  |
|  | ContentManager / PageManager / ArticleManager              |  |
|  | Doctrine ORM (entities, repositories)                      |  |
|  | StructureFactory (template/block introspection)            |  |
|  | SecurityChecker / AccessDecisionManager                    |  |
|  | WebspaceManager (webspace configuration)                   |  |
|  | MediaManager (file uploads and management)                 |  |
|  | NavigationManager (navigation structures)                  |  |
|  | TagManager / CategoryManager                               |  |
|  | SEAL Search (search engine abstraction layer)              |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
```

### Component Boundaries

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| **Symfony Firewall / Authenticator** | Extracts Bearer token from HTTP request, resolves to Sulu User, sets SecurityContext | Sulu UserRepository, Symfony Security |
| **Streamable HTTP Controller** | Accepts HTTP POST/GET/DELETE, delegates to MCP SDK transport layer, manages sessions | MCP Server, Symfony HttpKernel |
| **MCP Server (SDK)** | Protocol lifecycle: initialize, capability negotiation, request routing to tools/resources/prompts | Tool/Resource/Prompt registries |
| **Tool Handlers** | Execute CMS operations (CRUD pages, articles, blocks, media, etc.) | Sulu services (ContentManager, etc.), Permission Guard |
| **Resource Providers** | Expose read-only context data (templates, block types, sitemap, guidelines) | Sulu StructureFactory, WebspaceManager, Guidelines Service |
| **Prompt Generators** | Build system prompts from content guidelines and project context | Guidelines Service, WebspaceManager |
| **Permission Guard** | Checks Sulu user permissions before any tool execution | Sulu SecurityChecker, AccessDecisionManager |
| **Content Guidelines Service** | Stores/retrieves global and per-webspace content guidelines | Doctrine ORM (custom entity), configuration |
| **Block Type Discovery** | Introspects available block types from Sulu XML templates at runtime | Sulu StructureFactory |

### Data Flow

#### Tool Call (e.g., "Create a page")

```
1. AI Client sends HTTP POST to /_mcp
   Headers: Authorization: Bearer <token>, Mcp-Session-Id: <id>
   Body: {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"sulu_page_create","arguments":{...}}}

2. Symfony Firewall intercepts request
   - Custom authenticator extracts Bearer token
   - Resolves token to Sulu User (API key lookup or JWT validation)
   - Sets authenticated user in SecurityContext
   - Passes request to controller

3. Streamable HTTP Controller receives request
   - Looks up MCP session by Mcp-Session-Id header
   - Delegates to MCP SDK transport handler

4. MCP SDK routes JSON-RPC to registered tool handler
   - Parses "sulu_page_create" tool name
   - Validates input against JSON Schema (from PHP method signature)
   - Calls the tool handler method

5. PageCreateTool handler executes
   - Permission Guard checks: can this Sulu user create pages in this webspace?
   - If denied: returns MCP error response
   - If allowed: calls Sulu ContentManager/PageManager to create page
   - Returns MCP success response with created page data

6. Response flows back through transport -> HTTP response to AI client
   - Content-Type: application/json (single response)
   - OR Content-Type: text/event-stream (if streaming progress)
```

#### Resource Read (e.g., "Get available block types")

```
1. AI Client sends HTTP POST to /_mcp
   Body: {"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"sulu://blocks/types"}}

2. Auth + transport layers (same as above)

3. MCP SDK routes to BlockTypesResource provider

4. BlockTypesResource calls Sulu StructureFactory
   - Introspects all registered XML templates
   - Extracts block type definitions (name, properties, nested types)
   - Returns structured data as MCP resource content

5. Response: JSON with all block types and their schemas
```

#### Prompt Generation (e.g., "Get content guidelines")

```
1. AI Client sends HTTP POST to /_mcp
   Body: {"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"content_guidelines","arguments":{"webspace":"example","locale":"en"}}}

2. Auth + transport layers (same as above)

3. MCP SDK routes to ContentGuidelinesPrompt generator

4. Generator assembles prompt:
   - Loads global guidelines (tone, audience, style defaults)
   - Overlays webspace-specific overrides for "example" webspace
   - Formats as structured prompt messages

5. Response: Array of prompt messages the AI client uses as system context
```

## Component Architecture Detail

### 1. Bundle Registration & DI Configuration

The bundle follows standard Symfony bundle conventions with a DI extension that registers all services.

```
SuluMcpBundle/
  src/
    SuluMcpBundle.php                    # Bundle class
    DependencyInjection/
      SuluMcpExtension.php               # Loads services, configures MCP
      Configuration.php                   # Bundle config schema
    Controller/
      McpController.php                   # (only if custom routing needed beyond mcp-bundle)
    Tool/
      Page/
        PageGetTool.php                   # #[McpTool] - get page(s)
        PageCreateTool.php                # #[McpTool] - create page
        PageUpdateTool.php                # #[McpTool] - update page
        PageDeleteTool.php                # #[McpTool] - delete page
        PagePublishTool.php               # #[McpTool] - publish/unpublish
      Article/
        ArticleGetTool.php               # #[McpTool] - get article(s)
        ArticleCreateTool.php            # #[McpTool] - create article
        ArticleUpdateTool.php            # #[McpTool] - update article
        ArticleDeleteTool.php            # #[McpTool] - delete article
        ArticlePublishTool.php           # #[McpTool] - publish/unpublish
      Block/
        BlockAddTool.php                 # #[McpTool] - add block to page/article
        BlockRemoveTool.php              # #[McpTool] - remove block
        BlockReorderTool.php             # #[McpTool] - reorder blocks
      Media/
        MediaUploadTool.php              # #[McpTool] - upload media
        MediaListTool.php                # #[McpTool] - list media
        MediaDeleteTool.php              # #[McpTool] - delete media
      Snippet/
        SnippetGetTool.php               # #[McpTool] - get snippet(s)
        SnippetCreateTool.php            # #[McpTool] - create snippet
        SnippetUpdateTool.php            # #[McpTool] - update snippet
        SnippetDeleteTool.php            # #[McpTool] - delete snippet
      Tag/
        TagCreateTool.php                # #[McpTool] - create tag
        TagGetTool.php                   # #[McpTool] - get tag(s)
        TagDeleteTool.php                # #[McpTool] - delete tag
      Category/
        CategoryCreateTool.php           # #[McpTool] - create category
        CategoryGetTool.php              # #[McpTool] - get categories
        CategoryDeleteTool.php           # #[McpTool] - delete category
      Navigation/
        NavigationGetTool.php            # #[McpTool] - get navigation tree
    Resource/
      TemplateResource.php               # #[McpResource] - available page templates
      BlockTypeResource.php              # #[McpResourceTemplate] - block types per template
      SitemapResource.php                # #[McpResource] - full sitemap structure
      WebspaceConfigResource.php         # #[McpResource] - webspace configuration
      ContentGuidelinesResource.php      # #[McpResource] - content guidelines
      BusinessContextResource.php        # #[McpResource] - company/brand context
    Prompt/
      ContentGuidelinesPrompt.php        # #[McpPrompt] - assembled content guidelines
      SetupInstructionsPrompt.php        # #[McpPrompt] - AI client setup instructions
    Security/
      McpTokenAuthenticator.php          # Custom Symfony authenticator
      ApiTokenProvider.php               # Resolves tokens to Sulu users
    Service/
      ContentGuidelinesManager.php       # Guidelines CRUD and merging logic
      BlockTypeDiscovery.php             # Runtime block introspection
      PermissionGuard.php                # Sulu permission checking wrapper
      PromptExporter.php                 # Exports prompts for manual AI setup
    Entity/
      ContentGuideline.php               # Doctrine entity for guidelines
    Repository/
      ContentGuidelineRepository.php     # Doctrine repository
    config/
      services.yaml                      # Service definitions
      routes.yaml                        # MCP route registration
  config/
    packages/
      sulu_mcp.yaml                      # Default bundle configuration
```

### 2. MCP Tool Design Pattern

Each tool is a standalone PHP class with the `#[McpTool]` attribute. The Symfony MCP bundle auto-discovers these. Tools receive Sulu services via constructor DI.

```php
<?php

namespace Sulu\Bundle\McpBundle\Tool\Page;

use Mcp\Capability\Attribute\McpTool;
use Sulu\Bundle\McpBundle\Service\PermissionGuard;
// Sulu services injected via DI

class PageTools
{
    public function __construct(
        private readonly PageManagerInterface $pageManager,
        private readonly PermissionGuard $permissionGuard,
    ) {}

    #[McpTool(name: 'sulu_page_get', description: 'Get a page by UUID or list pages in a webspace')]
    public function getPage(
        string $webspace,
        string $locale,
        ?string $uuid = null,
        ?string $parentUuid = null,
        int $depth = 1,
    ): array {
        $this->permissionGuard->checkView('pages', $webspace, $locale, $uuid);

        if ($uuid) {
            $page = $this->pageManager->get($uuid, $locale);
            return ['page' => $this->serializePage($page)];
        }

        $pages = $this->pageManager->getChildren($parentUuid, $webspace, $locale, $depth);
        return ['pages' => array_map([$this, 'serializePage'], $pages)];
    }

    #[McpTool(name: 'sulu_page_create', description: 'Create a new page in a webspace')]
    public function createPage(
        string $webspace,
        string $locale,
        string $template,
        string $title,
        ?string $parentUuid = null,
        ?array $content = null,
    ): array {
        $this->permissionGuard->checkCreate('pages', $webspace, $locale);

        $page = $this->pageManager->create(
            webspace: $webspace,
            locale: $locale,
            template: $template,
            title: $title,
            parentUuid: $parentUuid,
            data: $content ?? [],
        );

        return ['page' => $this->serializePage($page), 'message' => 'Page created successfully'];
    }
}
```

### 3. MCP Resource Design Pattern

Resources expose read-only context data. They use `#[McpResource]` for static URIs or `#[McpResourceTemplate]` for parameterized URIs.

```php
<?php

namespace Sulu\Bundle\McpBundle\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;

class TemplateResource
{
    public function __construct(
        private readonly StructureFactoryInterface $structureFactory,
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {}

    #[McpResourceTemplate(
        uriTemplate: 'sulu://templates/{webspace}',
        name: 'page-templates',
        description: 'Available page templates for a webspace',
        mimeType: 'application/json',
    )]
    public function getTemplates(string $webspace): array {
        $webspaceConfig = $this->webspaceManager->findWebspaceByKey($webspace);
        $templates = $this->structureFactory->getStructures($webspaceConfig);

        return [
            'uri' => "sulu://templates/{$webspace}",
            'mimeType' => 'application/json',
            'text' => json_encode($this->serializeTemplates($templates)),
        ];
    }

    #[McpResource(
        uri: 'sulu://block-types',
        name: 'block-types',
        description: 'All available block types across all templates',
        mimeType: 'application/json',
    )]
    public function getBlockTypes(): array {
        // Introspects all XML template files to discover block type definitions
        $blockTypes = $this->blockTypeDiscovery->discoverAll();

        return [
            'uri' => 'sulu://block-types',
            'mimeType' => 'application/json',
            'text' => json_encode($blockTypes),
        ];
    }
}
```

### 4. MCP Prompt Design Pattern

Prompts assemble system context from content guidelines and project configuration.

```php
<?php

namespace Sulu\Bundle\McpBundle\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

class ContentGuidelinesPrompt
{
    public function __construct(
        private readonly ContentGuidelinesManager $guidelinesManager,
    ) {}

    #[McpPrompt(
        name: 'content_guidelines',
        description: 'Content guidelines for writing content in a specific webspace and locale',
    )]
    public function getGuidelines(
        string $webspace,
        string $locale,
    ): array {
        $guidelines = $this->guidelinesManager->resolve($webspace, $locale);

        return [
            [
                'role' => 'user',
                'content' => $this->formatGuidelines($guidelines),
            ],
        ];
    }
}
```

### 5. Authentication Architecture

The bundle creates a dedicated Symfony firewall for the MCP endpoint. Authentication maps a Bearer token to a Sulu User, so all subsequent operations respect Sulu's existing permission system.

**Authentication Flow:**

```
HTTP Request
  |
  v
Symfony Firewall "sulu_mcp" (pattern: ^/_mcp)
  |
  v
McpTokenAuthenticator
  |-- Extracts: Authorization: Bearer <token>
  |-- Looks up token via ApiTokenProvider
  |   |-- Option A: Database table (api_token -> user_id mapping)
  |   |-- Option B: JWT validation (signed by Sulu, contains user_id)
  |-- Returns: Symfony Passport with SuluUserBadge
  |
  v
Sulu User loaded into SecurityContext
  |
  v
All subsequent tool calls check permissions via Sulu's SecurityChecker
```

**Key design decision:** The bundle does NOT implement the full MCP OAuth 2.1 authorization flow. Instead, it uses a simpler Bearer token approach where tokens are pre-generated for Sulu users. This is because:

1. Sulu already has its own user/permission system
2. The MCP server runs inside the Sulu application (not as a separate service)
3. Full OAuth adds complexity inappropriate for a CMS bundle
4. Tokens can be generated/managed in Sulu's admin interface (future phase)

The MCP specification makes OAuth optional ("strongly recommended when..."), and for a bundle running inside an existing authenticated application, API key/token auth is the pragmatic choice.

### 6. Content Guidelines Architecture

Content guidelines are a new concept introduced by this bundle. They follow a global-defaults-with-webspace-overrides pattern.

```
ContentGuideline Entity
  |-- id (int)
  |-- webspace (string, nullable) -- null = global default
  |-- locale (string, nullable) -- null = all locales
  |-- tone (text) -- e.g., "professional but approachable"
  |-- audience (text) -- e.g., "B2B decision makers, 30-55"
  |-- style (text) -- e.g., "short paragraphs, active voice"
  |-- brandRules (text) -- e.g., "Always capitalize 'Acme Corp'"
  |-- additionalContext (json) -- extensible key-value pairs

ContentGuidelinesManager
  |-- resolve(webspace, locale) -> merges global + webspace-specific
  |-- get(webspace, locale) -> raw guidelines for a scope
  |-- save(ContentGuideline) -> persist
  |-- delete(id) -> remove
```

Resolution order: webspace+locale specific -> webspace default -> global+locale -> global default. Missing fields inherit from the next level up.

### 7. Block Type Discovery Architecture

Block types in Sulu are defined in XML template files. The bundle must introspect these at runtime to expose them as MCP resources.

```
BlockTypeDiscovery
  |-- discoverAll() -> all block types across all templates
  |-- discoverForTemplate(templateKey) -> block types for a specific template
  |-- getBlockSchema(blockTypeName) -> JSON Schema for a block type's properties
  |
  Uses: Sulu StructureFactory + XML template parsing

  Returns per block type:
  {
    "name": "text_editor",
    "title": "Text Editor",
    "properties": [
      {"name": "content", "type": "text_editor", "required": true},
      {"name": "caption", "type": "text_line", "required": false}
    ],
    "availableInTemplates": ["default", "homepage"]
  }
```

## Patterns to Follow

### Pattern 1: Tool Naming Convention

**What:** All MCP tools use a `sulu_` prefix with domain and action: `sulu_{domain}_{action}`
**When:** Every tool definition
**Why:** Prevents name collisions in multi-server MCP setups and makes tool purpose immediately clear to AI clients.

Examples: `sulu_page_get`, `sulu_page_create`, `sulu_article_publish`, `sulu_block_add`, `sulu_media_upload`

### Pattern 2: Permission-First Execution

**What:** Every tool handler checks permissions BEFORE executing any Sulu service call.
**When:** Every tool that modifies or reads content.
**Why:** The authenticated Sulu user's permissions must be respected. Never execute a Sulu operation and then check permissions -- fail fast.

```php
// CORRECT: Check first, execute second
public function createPage(...): array {
    $this->permissionGuard->checkCreate('pages', $webspace, $locale);
    $page = $this->pageManager->create(...);
    return [...];
}

// WRONG: Execute first, check later
public function createPage(...): array {
    $page = $this->pageManager->create(...); // DANGER: already modified state
    $this->permissionGuard->checkCreate('pages', $webspace, $locale);
    return [...];
}
```

### Pattern 3: Webspace and Locale as Required Parameters

**What:** Every content-related tool requires `webspace` and `locale` as explicit parameters.
**When:** Any tool that interacts with Sulu content (pages, articles, snippets, navigation).
**Why:** Sulu is inherently multi-webspace and multi-locale. There is no "default" -- the AI client must always specify context.

```php
#[McpTool(name: 'sulu_page_get')]
public function getPage(
    string $webspace,  // Always required
    string $locale,    // Always required
    ?string $uuid = null,
): array { ... }
```

### Pattern 4: Structured Error Responses

**What:** Return MCP-compliant error responses with actionable messages.
**When:** Permission denied, validation failure, entity not found.
**Why:** AI clients need clear error messages to understand what went wrong and potentially retry with corrected parameters.

```php
// Return structured errors that help the AI self-correct
return [
    'error' => true,
    'code' => 'PERMISSION_DENIED',
    'message' => 'User does not have permission to create pages in webspace "example". Required: sulu.webspaces.example.pages.add',
];
```

### Pattern 5: Resource URIs as Sulu Domain Addresses

**What:** MCP resource URIs follow `sulu://{domain}/{identifier}` pattern.
**When:** Defining all MCP resources and resource templates.
**Why:** Creates a consistent, discoverable namespace for all Sulu context data.

Examples:
- `sulu://templates/{webspace}` -- page templates for a webspace
- `sulu://block-types` -- all block types
- `sulu://sitemap/{webspace}/{locale}` -- sitemap tree
- `sulu://webspaces` -- all webspace configurations
- `sulu://guidelines/{webspace}/{locale}` -- content guidelines
- `sulu://context/business` -- company/brand context

## Anti-Patterns to Avoid

### Anti-Pattern 1: REST API Indirection

**What:** Calling Sulu's REST admin API endpoints from within the bundle.
**Why bad:** Adds HTTP overhead, serialization/deserialization costs, and bypasses the DI container. The bundle runs inside Sulu -- it has direct access to all services.
**Instead:** Inject Sulu services directly and call PHP methods.

### Anti-Pattern 2: Monolithic Tool Classes

**What:** Putting all page tools, article tools, media tools etc. in a single large class.
**Why bad:** Violates SRP, makes testing difficult, creates merge conflicts, harder to navigate.
**Instead:** One class per domain (PageTools, ArticleTools) or one class per action (PageCreateTool, PageGetTool). Domain grouping is the recommended middle ground.

### Anti-Pattern 3: Hardcoded Block Types

**What:** Maintaining a static list of block types in PHP code.
**Why bad:** Every Sulu project defines its own block types in XML. A hardcoded list would be wrong for most projects.
**Instead:** Use BlockTypeDiscovery to introspect block types at runtime from Sulu's StructureFactory.

### Anti-Pattern 4: Session-Scoped User State

**What:** Storing the authenticated user in the MCP session rather than resolving per-request.
**Why bad:** Token expiration/revocation would not be caught. User permissions could change between requests.
**Instead:** Resolve the user from the Bearer token on every HTTP request via the Symfony firewall. The MCP session manages protocol state, not authentication state.

### Anti-Pattern 5: Bypassing Sulu's Permission System

**What:** Directly calling Doctrine repositories to CRUD content, skipping Sulu's managers.
**Why bad:** Sulu managers enforce business rules, event dispatching, search indexing, and permission checks. Bypassing them creates data inconsistency.
**Instead:** Always use Sulu's high-level manager services (PageManager, ArticleManager, etc.) which handle the full lifecycle.

## Scalability Considerations

| Concern | MVP (single user) | Growth (10 concurrent AI sessions) | Scale (50+ concurrent) |
|---------|-------------------|-------------------------------------|------------------------|
| Session storage | File-based (`%kernel.cache_dir%/mcp-sessions`) | Cache-based (Redis via Symfony Cache) | Redis with TTL and eviction |
| Auth token resolution | Database lookup per request | Add caching layer (1-5 min TTL) | Token caching with background refresh |
| Block type discovery | Introspect on every resource read | Cache with invalidation on template changes | Warm cache on deployment |
| Content guidelines | Database query per prompt request | Cache resolved guidelines (5 min TTL) | Cache with event-based invalidation |
| Concurrent writes | No locking (out of scope for v1) | Monitor for conflicts, add advisory logging | Consider optimistic locking per entity |

## Suggested Build Order

Based on component dependencies, the recommended build order is:

### Phase 1: Foundation (no MCP yet)
1. **Bundle skeleton** -- SuluMcpBundle class, DI extension, configuration schema
2. **Content Guidelines entity + service** -- Doctrine entity, repository, manager (can be tested independently)

**Rationale:** These have zero external dependencies and establish the project structure.

### Phase 2: Transport & Auth
3. **Authentication** -- McpTokenAuthenticator, ApiTokenProvider, firewall config
4. **MCP bundle integration** -- Install symfony/mcp-bundle, configure transport, verify endpoint responds

**Rationale:** Auth must work before any tools can be tested. MCP transport must be functional to test anything MCP-related.

### Phase 3: Core Tools (read operations first)
5. **Page tools** -- get/list pages (read-only first, then create/update/delete/publish)
6. **Permission Guard** -- wrap Sulu SecurityChecker, integrate into tools

**Rationale:** Pages are the most fundamental Sulu content type. Read operations validate the full stack (auth -> MCP -> Sulu service -> response) with minimal risk.

### Phase 4: Resources & Discovery
7. **Block type discovery** -- StructureFactory introspection
8. **MCP resources** -- templates, block types, webspace config, sitemap

**Rationale:** Resources provide context that makes tools useful. Block discovery enables the AI to know what blocks are available.

### Phase 5: Remaining Tools
9. **Article tools** -- CRUD + publish
10. **Block tools** -- add, remove, reorder
11. **Snippet tools** -- CRUD
12. **Media tools** -- upload, list, delete
13. **Tag/Category tools** -- CRUD
14. **Navigation tools** -- read navigation tree

**Rationale:** Expand tool coverage. Each domain is relatively independent once the pattern is established.

### Phase 6: Prompts & Guidelines
15. **Content guidelines prompt** -- assemble guidelines into MCP prompt
16. **Setup instructions prompt** -- exportable instructions for AI client setup
17. **Prompt exporter** -- generate standalone files for manual AI client configuration

**Rationale:** Prompts depend on the guidelines service (Phase 1) and MCP integration (Phase 2) being stable.

## Key Technology Decisions

### symfony/mcp-bundle + symfony/mcp-sdk (official)

The official Symfony MCP bundle is the correct foundation. It provides:
- Auto-discovery of tools, resources, prompts via PHP 8 attributes
- Streamable HTTP transport with session management
- Integration with Symfony's DI container
- Profiler integration for debugging

**Confidence:** HIGH -- This is Symfony's official, Anthropic-endorsed MCP implementation.

### Streamable HTTP (not legacy SSE)

The MCP specification deprecated the legacy HTTP+SSE transport in favor of Streamable HTTP (spec version 2025-03-26, refined in 2025-06-18). Streamable HTTP uses a single endpoint, supports both JSON responses and SSE streams, and handles session management via the `Mcp-Session-Id` header.

**Confidence:** HIGH -- This is the current MCP specification standard.

### Bearer Token Auth (not full OAuth 2.1)

For v1, use simple Bearer token authentication that maps to Sulu users. The full OAuth 2.1 flow (with Protected Resource Metadata, Dynamic Client Registration, etc.) is overkill for a CMS bundle that runs inside an existing authenticated application.

**Confidence:** MEDIUM -- May need to revisit if MCP clients begin requiring OAuth discovery. The architecture allows adding OAuth metadata endpoints later without changing the core auth mechanism.

### Doctrine Entity for Content Guidelines

Content guidelines are stored as a Doctrine entity rather than YAML/config files because:
- They may be edited at runtime (future admin UI)
- Per-webspace overrides need dynamic resolution
- Config files would require cache clearing on changes

**Confidence:** HIGH -- Standard Symfony/Sulu pattern for runtime-configurable data.

## Sources

- [MCP Specification: Transports (2025-06-18)](https://modelcontextprotocol.io/specification/2025-06-18/basic/transports) -- Streamable HTTP transport specification
- [MCP Architecture Overview](https://modelcontextprotocol.io/docs/learn/architecture) -- Host/Client/Server model, primitives
- [MCP Authorization Tutorial](https://modelcontextprotocol.io/docs/tutorials/security/authorization) -- OAuth 2.1 flow and Bearer token patterns
- [Symfony MCP Bundle Documentation](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html) -- PHP attributes, configuration, transport setup
- [Symfony MCP Bundle (GitHub)](https://github.com/symfony/mcp-bundle) -- Source code and examples
- [Official PHP MCP SDK (GitHub)](https://github.com/modelcontextprotocol/php-sdk) -- SDK architecture and classes
- [Symfony Blog: Official MCP SDK Announcement](https://symfony.com/blog/symfony-to-provide-the-official-mcp-sdk) -- Partnership context
- [Sulu 3.0 Release Blog](https://sulu.io/blog/sulu-3-0-released) -- New content storage architecture
- [Sulu 3.0 Content Storage Preview](https://sulu.io/blog/3-0-preview-how-sulu-cms-is-evolving-its-content-storage-architecture) -- Doctrine ORM entities replacing PHPCR
- [Sulu 3.0 UPGRADE Guide](https://github.com/sulu/sulu/blob/3.0/UPGRADE-3.x.md) -- Service changes, bundle restructuring
- [Sulu SecurityBundle](https://docs.sulu.io/en/latest/bundles/security/) -- Sulu permission system, voters, security contexts
- [MCP Architecture Deep Dive: Tools, Resources, Prompts](https://www.getknit.dev/blog/mcp-architecture-deep-dive-tools-resources-and-prompts-explained) -- Primitive design patterns
- [MCP Best Practices](https://modelcontextprotocol.info/docs/best-practices/) -- Server implementation patterns
- [Why MCP Deprecated SSE](https://blog.fka.dev/blog/2025-06-06-why-mcp-deprecated-sse-and-go-with-streamable-http/) -- Transport evolution rationale
