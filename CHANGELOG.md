# Changelog

All notable changes to `masilia/ibexa-ai-editor` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

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

[Unreleased]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.1...HEAD
[0.2.1]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/MasiliaLabs/ibexa-ai-editor/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-ai-editor/releases/tag/0.1.0
