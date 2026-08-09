# Security Policy

## Supported Versions

No stable version has been tagged yet. Until `1.0.0` is released, security
reports should reference the `main` branch.

| Version | Supported          |
|---------|--------------------|
| main    | :white_check_mark: |

See also documentation about the [Release Cycle](https://sulu.io/direction#our-release-cycle).

## Reporting a Vulnerability

You can contact us for security related issues by using [security@sulu.io](mailto:security@sulu.io).

Please do not open a public issue for security reports.

## Scope

This bundle exposes Sulu content management over the Model Context Protocol and
authenticates through Sulu's own user system. Reports about the following are
especially relevant:

* Any operation reachable over MCP that a user cannot perform in the Sulu admin UI.
* Bypasses of the `dangerous_tools` configuration.
* Issues in the OAuth 2.1 authorization flow, the consent screen, or dynamic
  client registration.
