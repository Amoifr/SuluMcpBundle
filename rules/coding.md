# Coding Rules

- declare(strict_types=1) mandatory
- Symfony coding standard (@Symfony)
- readonly services
- Constructor property promotion required
- No public entity properties
- Doctrine attributes only (for any entities introduced by this bundle)

After change:
1) composer fix
2) composer lint
3) composer test
