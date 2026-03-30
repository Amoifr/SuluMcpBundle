<!-- GSD:project-start source:PROJECT.md -->
## Project

**Sulu MCP Server**

A Symfony bundle for Sulu CMS 3.x that exposes content management operations as MCP (Model Context Protocol) tools via HTTP/SSE. It enables AI assistants like Claude.ai and ChatGPT to manage Sulu content directly — creating pages, adding blocks, publishing articles, managing media, and more — while respecting Sulu's user permissions and multi-webspace architecture.

**Core Value:** AI assistants can create, edit, and publish content in Sulu CMS with full awareness of the project's content guidelines, templates, and brand context — writing on-brand content, not just executing CRUD.

### Constraints

- **Tech Stack**: PHP 8.2+, Symfony bundle, Sulu 3.x — no external runtime dependencies
- **Auth**: Must use Sulu's native user authentication — no separate auth system
- **Transport**: HTTP/SSE only — designed for network-accessible deployments
- **Permissions**: All operations must respect the authenticated Sulu user's permissions — no privilege escalation
<!-- GSD:project-end -->

<!-- GSD:stack-start source:research/STACK.md -->
## Technology Stack

## Recommended Stack
### Core Technologies
| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP | ^8.2 | Runtime | Sulu 3.0 minimum. Provides attributes, enums, fibers, readonly properties. |
| Symfony | ^7.3 | Framework | symfony/mcp-bundle requires ^7.3. Sulu 3.0 supports 6.4-7.4 so ^7.3 is the intersection. |
| Sulu CMS | ^3.0 | Content Management System | Target platform. New Doctrine-based content storage, hexagonal architecture with message bus. |
| mcp/sdk | ^0.4 | MCP Protocol SDK | Official PHP SDK for Model Context Protocol. Maintained by Symfony team + PHP Foundation + Anthropic. Framework-agnostic. Supports MCP spec 2025-03-26. |
| symfony/mcp-bundle | ^0.6 | Symfony MCP Integration | Official Symfony bundle wrapping mcp/sdk. Provides attribute-based tool/resource/prompt discovery, streamable HTTP transport, session management, routing, profiler integration. |
### Sulu 3.0 Service Layer (Content Operations)
#### New Packages (Hexagonal Architecture)
| Package | Namespace | Key Messages | Purpose |
|---------|-----------|-------------|---------|
| Page | `Sulu\Page\Application\Message\` | `CreatePageMessage`, `ModifyPageMessage`, `RemovePageMessage`, `ApplyWorkflowTransitionPageMessage`, `MovePageMessage`, `OrderPageMessage`, `CopyPageMessage`, `CopyLocalePageMessage`, `RemovePageTranslationMessage`, `RestorePageVersionMessage` | Full page lifecycle: CRUD, publish/unpublish (via workflow transition), move, reorder, copy, locale copy, version restore |
| Article | `Sulu\Article\Application\Message\` | `CreateArticleMessage`, `ModifyArticleMessage`, `RemoveArticleMessage`, `ApplyWorkflowTransitionArticleMessage`, `CopyLocaleArticleMessage`, `RemoveArticleTranslationMessage`, `RestoreArticleVersionMessage` | Full article lifecycle: CRUD, publish/unpublish, locale copy, version restore |
| Snippet | `Sulu\Snippet\Application\Message\` | `CreateSnippetMessage`, `ModifySnippetMessage`, `RemoveSnippetMessage`, `ApplyWorkflowTransitionSnippetMessage`, `CopyLocaleSnippetMessage`, `ModifySnippetAreaMessage`, `RemoveSnippetAreaMessage`, `RemoveSnippetTranslationMessage`, `RestoreSnippetVersionMessage` | Full snippet lifecycle plus snippet area management |
| Content | `Sulu\Content\Application\` | ContentManager, ContentResolver, ContentPersister, ContentNormalizer, ContentWorkflow, ContentAggregator, ContentCopier, ContentMerger, ContentEnhancer, ContentMetadataInspector, MetadataResolver, PropertyResolver, DimensionContentCollectionFactory | Core content infrastructure shared across page/article/snippet. ContentManager is the primary facade. |
#### Traditional Bundles (Still in src/Sulu/Bundle/)
| Bundle | Key Service Interface | Namespace | Purpose |
|--------|----------------------|-----------|---------|
| MediaBundle | `MediaManagerInterface` | `Sulu\Bundle\MediaBundle\Media\Manager\` | Upload, list, move, delete media. Methods: `save()`, `get()`, `getById()`, `getByIds()`, `delete()`, `move()`, `addFormatsAndUrl()`, `getFormatUrls()` |
| TagBundle | `TagManagerInterface` | `Sulu\Bundle\TagBundle\Tag\` | Create, get, delete tags. Also `TagRepositoryInterface` for data access. |
| CategoryBundle | `CategoryManagerInterface` | `Sulu\Bundle\CategoryBundle\Category\` | Create, get, delete categories. Also `KeywordManagerInterface` for keyword operations. |
| SecurityBundle | Symfony Security integration | `Sulu\Bundle\SecurityBundle\` | User authentication, role-based permissions, security contexts. Integrates with Symfony firewall system. |
| WebsiteBundle | Navigation, sitemap | `Sulu\Bundle\WebsiteBundle\` | Navigation trees (`sulu_page_navigation_tree`), sitemap generation. In 3.0 controller moved to `Sulu\Content\UserInterface\Controller\Website\ContentController`. |
| AdminBundle | Webspace configuration | `Sulu\Bundle\AdminBundle\` | Admin metadata, webspace configuration access. |
| CoreBundle | Webspace manager | `Sulu\Component\Webspace\Manager\WebspaceManagerInterface` | Webspace definitions, locales, URLs, template configuration. |
### Supporting Libraries
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| sulu/messenger | (bundled) | Message bus middleware | Used internally by Sulu for `EnableFlushStamp` and `DoctrineFlushMiddleware`. Already available in Sulu projects. |
| doctrine/orm | ^2.17 or ^3.3 | ORM | Sulu 3.0 dependency. Content stored as Doctrine entities with JSON columns. |
| schranz-search/seal | (bundled) | Search abstraction | Sulu 3.0 uses SEAL instead of custom search. Default adapter is Loupe (PHP-native SQLite search). Useful for content discovery tools. |
| symfony/event-dispatcher | ^7.3 | Domain events | Listen to `PageCreatedEvent`, `ArticleModifiedEvent`, etc. for reactive tools. |
| symfony/security-bundle | ^7.3 | Authentication | Sulu firewall integration for MCP endpoint authentication. |
### Development Tools
| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit | ^10.5 or ^11.5 | Testing | Sulu 3.0 compatible versions. |
| PHPStan | ^2.0 | Static analysis | Already used by Sulu. Follow project conventions. |
| PHP-CS-Fixer | ^3.14 | Code style | Already used by Sulu. Follow project conventions. |
| symfony/profiler-pack | Debug | MCP bundle adds dedicated profiler panel showing registered tools/resources/prompts. |
## MCP Protocol Details
### Transport: Streamable HTTP (not legacy SSE)
- Single HTTP endpoint (default `/_mcp`) supporting POST and GET
- JSON-RPC 2.0 over HTTP POST for requests
- Server can optionally use SSE to stream multiple responses
- Session management via `Mcp-Session-Id` header (cryptographically secure)
- Stateless-capable: can run behind load balancers
- The `symfony/mcp-bundle` implements `StreamableHttpTransport` from `mcp/sdk`
# config/packages/mcp.yaml
# config/routes.yaml
### Defining MCP Capabilities
## Authentication Strategy
- No separate auth system to maintain
- MCP user = Sulu admin user with their existing roles/permissions
- Operations that the user cannot perform in the admin UI will also fail via MCP
## Installation
# Core MCP packages
# This pulls in mcp/sdk automatically as a dependency
# Sulu 3.0 (already installed in target project)
# composer require sulu/sulu:"^3.0"
## Alternatives Considered
| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `symfony/mcp-bundle` + `mcp/sdk` | `php-mcp/server` | If you need Laravel integration or cannot use Symfony 7.3+. Has ReactPHP dependency which adds complexity. No Symfony bundle adapter exists. |
| `symfony/mcp-bundle` + `mcp/sdk` | `logiscape/mcp-sdk-php` | If deploying to shared hosting (cPanel/Apache) without Symfony. Pure PHP, no framework dependency. Includes OAuth 2.1 support. Not needed when Symfony is already the foundation. |
| `symfony/mcp-bundle` + `mcp/sdk` | Build MCP protocol from scratch | Never. The protocol involves JSON-RPC 2.0, session management, capability negotiation, and transport handling. The official SDK handles this correctly. |
| Sulu services directly | Sulu REST API | Never for this project. Direct service access is faster, gives full control, and the PROJECT.md explicitly excludes REST API indirection. |
| Streamable HTTP transport | Legacy HTTP+SSE transport | Never. SSE transport was deprecated in MCP spec 2025-03-26. Streamable HTTP is the replacement with better load balancing and stateless support. |
## What NOT to Use
| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `php-mcp/server` | Adds ReactPHP dependency, no Symfony bundle, would require manual integration work. The official `mcp/sdk` + `symfony/mcp-bundle` is the canonical choice for Symfony projects. | `symfony/mcp-bundle` (^0.6) |
| `logiscape/mcp-sdk-php` | Designed for vanilla PHP/shared hosting. Sulu is a full Symfony application; use the official Symfony integration. | `symfony/mcp-bundle` (^0.6) |
| PHPCR/Jackalope services | Completely removed in Sulu 3.0. All content storage is Doctrine ORM with JSON columns. Any code referencing `DocumentManager`, `PageDocument`, or PHPCR classes will not work. | Sulu 3.0 message bus + ContentManager |
| Sulu 2.x `DocumentManager` | Replaced by the message-based architecture in `/packages/`. `DocumentManager` no longer exists for pages, articles, snippets. | `CreatePageMessage`, `ModifyPageMessage`, etc. dispatched via `MessageBusInterface` |
| Legacy HTTP+SSE MCP transport | Deprecated in MCP spec 2025-03-26. Replaced by Streamable HTTP which is simpler and supports stateless deployment. | Streamable HTTP (the default in `symfony/mcp-bundle`) |
| Separate user authentication | Sulu already has a full security system with users, roles, and permissions. Building a separate auth mechanism creates maintenance burden and permission drift. | Sulu's SecurityBundle + Symfony firewall for MCP endpoint |
| Elasticsearch for articles | Sulu 3.0 ArticleBundle no longer requires Elasticsearch. Works with Doctrine alone. SEAL (with Loupe adapter) handles search. | Doctrine ORM queries + SEAL for full-text search if needed |
## Stack Patterns by Variant
- Use `symfony/mcp-bundle` directly (requires ^7.3)
- This is the expected case for new Sulu 3.0 projects
- `symfony/mcp-bundle` requires ^7.3, so it will NOT work on 6.4
- Option A: Upgrade Symfony to 7.3+ (recommended -- Sulu 3.0 supports it)
- Option B: Use `mcp/sdk` directly without the bundle wrapper, implementing transport/routing manually
- This is an important constraint to document and validate early
- Use a custom Doctrine entity for guidelines (global + per-webspace overrides)
- Expose via MCP resource (`McpResource` attribute)
- No existing Sulu concept for this -- the bundle introduces it as a new feature
- Streamable HTTP transport works with MCP gateways since it is standard HTTP
- Ensure the `/_mcp` endpoint is accessible from the gateway's network
- Gateway handles protocol translation; no special server-side changes needed
## Version Compatibility
| Package | Compatible With | Notes |
|---------|-----------------|-------|
| symfony/mcp-bundle ^0.6 | mcp/sdk ^0.4 | Bundle depends on SDK. Both experimental (pre-1.0). Expect API changes. |
| symfony/mcp-bundle ^0.6 | Symfony ^7.3 | **Not compatible with Symfony 6.4.** Sulu 3.0 projects must use Symfony 7.3+. |
| mcp/sdk ^0.4 | PHP ^8.1 | SDK supports 8.1+, but Sulu requires 8.2+. Effective minimum is PHP 8.2. |
| sulu/sulu ^3.0 | PHP ^8.2, Symfony ^6.4 or ^7.1 | Broad compatibility, but MCP bundle narrows to Symfony ^7.3. |
| sulu/sulu ^3.0 | doctrine/orm ^2.17 or ^3.3 | New content storage uses Doctrine entities with JSON columns. |
| mcp/sdk ^0.4 | MCP spec 2025-03-26 | Streamable HTTP transport. Resource templates awaiting SDK support (issue #9). |
## Pre-1.0 SDK Risk Assessment
- **API may change** between minor versions
- **Backward compatibility** is not guaranteed until 1.0
- **Resource templates** are defined in the spec but not yet functional in the SDK (issue #9)
- **Mitigation:** Pin exact versions in composer.json, wrap SDK usage behind internal interfaces so refactoring on SDK changes is localized
- **Positive signal:** Maintained by Symfony team + PHP Foundation + Anthropic. Active development (v0.6 released 2026-03-04). The SDK is the canonical choice despite pre-1.0 status.
## Sulu 3.0 Content Architecture Summary
## Sources
- [Official MCP PHP SDK (modelcontextprotocol/php-sdk)](https://github.com/modelcontextprotocol/php-sdk) -- v0.4.0, released 2026-02-23
- [symfony/mcp-bundle (Packagist)](https://packagist.org/packages/symfony/mcp-bundle) -- v0.6.0, released 2026-03-04
- [MCP Bundle Documentation (Symfony Docs)](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html) -- transport, attributes, configuration
- [Announcing Official PHP SDK for MCP (PHP Foundation)](https://thephp.foundation/blog/2025/09/05/php-mcp-sdk/) -- background on SDK selection
- [MCP Spec 2025-03-26 Transports](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports) -- Streamable HTTP replacing SSE
- [Sulu 3.0 Released](https://sulu.io/blog/sulu-3-0-released) -- architecture changes, Doctrine ORM replacement
- [Sulu 3.0 composer.json](https://github.com/sulu/sulu/blob/3.0/composer.json) -- PHP ^8.2, Symfony ^6.4 or ^7.1
- [Sulu 3.0 UPGRADE-3.x.md](https://github.com/sulu/sulu/blob/3.0/UPGRADE-3.x.md) -- namespace changes, new bundle structure
- [Sulu 3.0 packages/ directory](https://github.com/sulu/sulu/tree/3.0/packages) -- page, article, snippet, content, route, search, custom-url
- [Sulu 3.0 Page Messages](https://github.com/sulu/sulu/tree/3.0/packages/page/src/Application/Message) -- 10 message classes for page operations
- [Sulu 3.0 Article Messages](https://github.com/sulu/sulu/tree/3.0/packages/article/src/Application/Message) -- 7 message classes for article operations
- [Sulu 3.0 Snippet Messages](https://github.com/sulu/sulu/tree/3.0/packages/snippet/src/Application/Message) -- 9 message classes for snippet operations
- [Sulu 3.0 Content Application Services](https://github.com/sulu/sulu/tree/3.0/packages/content/src/Application) -- ContentManager, ContentResolver, etc.
- [Sulu 3.0 MediaManagerInterface](https://github.com/sulu/sulu/blob/3.0/src/Sulu/Bundle/MediaBundle/Media/Manager/MediaManagerInterface.php) -- media operations
- [SuluContentBundle Service Naming RFC](https://github.com/sulu/SuluContentBundle/issues/52) -- ContentManager chosen as facade name
- [php-mcp/server](https://github.com/php-mcp/server) -- alternative SDK, not recommended for Symfony projects
- [logiscape/mcp-sdk-php](https://github.com/logiscape/mcp-sdk-php) -- alternative SDK for vanilla PHP, not recommended
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

Conventions not yet established. Will populate as patterns emerge during development.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

Architecture not yet mapped. Follow existing patterns found in the codebase.
<!-- GSD:architecture-end -->

<!-- GSD:agents-start source:AGENTS.md -->
## Agent Discipline

Follow AGENTS.md strictly.

### Output discipline

When proposing changes:

- Show minimal diffs only.
- List modified files explicitly.
- Do not refactor beyond the requested scope.
- Do not rewrite entire files unless necessary.

### Verification (mandatory in response)

After code changes, state explicitly which of these were run:

- composer fix
- composer lint
- composer phpstan
- composer test

### Architecture discipline

- Tool classes must not access Doctrine or persistence directly — delegate to Sulu services.
- Follow rules/* without exception.

### Clarification rule

If a request would violate architecture, strict-mode, or other rules:
Ask one precise clarification question before implementing.

Do not guess.
<!-- GSD:agents-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
