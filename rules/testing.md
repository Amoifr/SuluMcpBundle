# Testing Rules

Layer mapping:
- Tool logic (pure unit) → Unit tests
- DI / kernel integration / Sulu service wiring → Functional/kernel tests

Required:
- final test classes
- #[CoversClass]
- Prophecy only (for mock-based unit tests)
- Use factories where available
- Test happy + exception paths
