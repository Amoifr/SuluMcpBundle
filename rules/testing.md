# Testing Rules

Layer mapping:
- Tool logic (pure unit, mocked collaborators) → tests/Unit
- Real service wiring, no kernel boot → tests/Integration
- DI compilation / kernel boot / Doctrine-backed behaviour → tests/Functional (boots the tests/Application kernel)

phpunit testsuites: `Unit` (tests/Unit + tests/Integration), `Functional` (tests/Functional).
Run one suite: `vendor/bin/phpunit --testsuite Unit` or `--testsuite Functional`.

Required:
- final test classes
- #[CoversClass] (unit/integration) / #[CoversNothing] (cross-cutting functional)
- PHPUnit native mocks (MockObject) for mock-based unit tests
- Use factories where available
- Test happy + exception paths
