# Changelog

All notable changes to `masilia/ibexa-ai-editor` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

### Added
- Docs: a *Stay informed* section in the README: how to be told of new releases (GitHub Watch, the releases feed, `composer outdated`, Dependabot) and where to read what changes.

## [0.2.5] - 2026-11-19

### Added
- Docs: a *With Symfony Flex* section in the README and in the skill: the Masilia recipes (`MasiliaLabs/recipes`) enable the bundle and write its route and config files on `composer require`.

## [0.2.4] - 2026-11-19

### Fixed
- Install docs: the admin is built with `php bin/console ibexa:encore:compile` (`yarn encore dev --config-name ibexa` does not exist on a blank project); README and skill corrected.

## [0.2.3] - 2026-11-19

### Added
- An agent skill, `skills/masilia-ibexa-ai-editor`, for AI coding agents that work with the package; the README says how to install it.

### Changed
- The Masilia dependencies come from Packagist: the `repositories` entries of `composer.json` are gone.

## [0.2.2] - 2026-11-17

### Changed
- Declares the dependencies the code uses directly; examples and test fixtures are neutral.

## [0.2.1] - 2026-11-15

### Fixed
- The image model gets a brief (subject, what the item is about, no text, screenshot or interface) instead of a list of form fields.

## [0.2.0] - 2026-11-14

### Changed
- **Breaking:** the admin controllers are Ibexa's (access checks, admin routes in the siteaccess group, the admin's CSRF token); the implementation moves to `src/lib`; requires `masilia/ibexa-ai` `^2.0`.

## [0.1.0] - 2026-11-14

First release: split from `masilia/ibexa-ai`. AI help in the content editing form: a suggestion for each field and image generation. It uses only the contracts of the OS.

[Unreleased]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.5...HEAD
[0.2.5]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.4...0.2.5
[0.2.4]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.3...0.2.4
[0.2.3]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.2...0.2.3
[0.2.2]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-ai-editor/releases/tag/0.1.0
