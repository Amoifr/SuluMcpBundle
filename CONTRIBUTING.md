Contributing
------------

Sulu MCP Bundle is an open source, community-driven project. We follow the same
coding standards as Symfony.

Before making a pull request please ensure you use the [Pull Request
Template](.github/PULL_REQUEST_TEMPLATE.md).

Run the following, in this order, before opening a pull request:

```bash
composer fix     # rector + php-cs-fixer
composer lint    # phpstan + cs check + rector dry-run + composer validate
composer test    # phpunit
```

Never skip `composer fix` — the license header and code style are enforced by
`composer lint`, and a missing header fails the build.

Useful links:

* [Creating a Pull Request](https://docs.sulu.io/en/3.x/developer/contributing/index.html): Sulu specific Pull Request Guide.
* [Coding Standards](http://symfony.com/doc/current/contributing/code/index.html): General Symfony coding standards.
* [General Developer Documentation](https://docs.sulu.io/): General Sulu Developer documentation index.
