# AGENTS.md — Read first

## Identity
PHP + Symfony bundle for Sulu CMS 3.x.
No Docker local dev setup — this is a library, not an application.

## Console
Use `php bin/console` in projects that install this bundle.
Use `vendor/bin/phpunit` directly for tests in this repo.

## Mandatory workflow (order required)
1) composer fix
2) composer lint
3) composer test

Never skip `fix`.

## Core workflow
Start every feature with:
"Let me research the codebase and create a plan before implementing."

1) Research — understand existing patterns and architecture.
2) Plan — propose approach and confirm.
3) Implement — build with tests and error handling.
4) Validate — ALWAYS run formatters, linters, and tests.

## Code organization
- Keep functions small and focused.
- If comments are needed to explain structure, split into functions.
- Group related functionality clearly.
- Prefer many small files over few large ones.

### Prefer explicit over implicit
- Clear names over clever abstractions.
- Obvious data flow over hidden magic.
- Direct dependencies over service locators.

## Rules
- rules/architecture.md
- rules/coding.md
- rules/commits.md
- rules/testing.md
- rules/tooling.md
- rules/strict-mode.md

## Hard stop
If a change conflicts with any rule, STOP and ask one focused question.
