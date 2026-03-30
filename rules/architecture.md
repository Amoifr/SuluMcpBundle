# Architecture

Bundle layer mapping:
- Tool classes (MCP tools): Application layer — orchestrate Sulu services, no business logic.
- Sulu service adapters / DI wiring / configuration: Infrastructure layer.
- No separate Domain layer — Sulu provides the domain.

- One class per file.
- declare(strict_types=1) required.
- Tool classes must not access Doctrine or persistence directly — delegate to Sulu services.
- No business logic in DependencyInjection or configuration classes.
