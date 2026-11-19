# Ibexa AI Editor

[![CI](https://github.com/MasiliaLabs/ibexa-ai-editor/actions/workflows/ci.yml/badge.svg)](https://github.com/MasiliaLabs/ibexa-ai-editor/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/masilia/ibexa-ai-editor.svg)](https://packagist.org/packages/masilia/ibexa-ai-editor)
[![Downloads](https://img.shields.io/packagist/dt/masilia/ibexa-ai-editor.svg)](https://packagist.org/packages/masilia/ibexa-ai-editor/stats)
[![PHP](https://img.shields.io/packagist/php-v/masilia/ibexa-ai-editor.svg)](https://packagist.org/packages/masilia/ibexa-ai-editor)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

AI help inside the Ibexa content editing form: a suggestion for each field, and image generation.

`masilia/ibexa-ai-editor` is part of a family of packages for [Ibexa](https://www.ibexa.co) that work together or alone. See [the other packages](#the-masilia-packages).

## What it is

AI help inside the Ibexa **content editing form**: next to each field an editor can ask for a suggestion, and for image fields generate a picture from a short brief. It uses the providers and the rules of [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) — it talks to it only through its contracts.

## Features

- **A suggestion for each field**: pick one of the actions (the server applies its own instruction and its faithfulness rules) or write your own prompt. The text streams into a modal and rich text is sanitised before it reaches the editor.
- **Image generation** for image fields: the model gets a brief built from the content (what the item is about), not from a list of form fields.
- **Admin-native**: controllers use Ibexa's access checks and the admin's CSRF token, routes belong to the admin siteaccess group.
- **Same providers as the assistant**: whatever provider answers for the siteaccess in `masilia/ibexa-ai` is used here.

## Requirements

- PHP 8.3 or later
- Ibexa 5.0 (Symfony 7.4)

Installed with it by Composer:

- [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) the providers, the rules and the contracts

## Installation

```bash
composer require masilia/ibexa-ai-editor
```

1. Enable the bundles in `config/bundles.php` (`masilia/ibexa-ai` must be set up first, see its README):

   ```php
   Masilia\Bundle\IbexaAiEditor\MasiliaIbexaAiEditorBundle::class => ['all' => true],
   ```

2. Import the routes (`config/routes/masilia_ibexa_ai_editor.yaml`):

   ```yaml
   masilia_ibexa_ai_editor:
       resource: '@MasiliaIbexaAiEditorBundle/Resources/config/routes.yaml'
   ```

3. Build the admin assets:

   ```bash
   yarn ibexa-generate-tsconfig
   php bin/console ibexa:encore:compile
   ```

   Inside DDEV, prefix each command with `ddev exec`. The admin build is `ibexa:encore:compile`: `yarn encore dev --config-name ibexa` does not work ("Configuration with the name ibexa was not found"), and a plain `yarn encore dev` does not build the admin on a project that has not merged the Ibexa configuration into its `webpack.config.js`.

## Usage

Edit a content in the admin. Open the AI help of a field, pick an action or write a prompt, read the suggestion as it streams, and accept it into the field. Image generation needs a provider that supports images (set its image model in **AI Assistant**).

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```

The tests of the admin scripts run with Node (see [CONTRIBUTING.md](CONTRIBUTING.md)).

## AI agent skill

This package ships a skill for AI coding agents (Claude Code and other agents that read `SKILL.md` skills): how to install, configure, operate and troubleshoot `masilia/ibexa-ai-editor`. It lives in [`skills/masilia-ibexa-ai-editor`](skills/masilia-ibexa-ai-editor/SKILL.md) and comes with the package.

```bash
# For the project (committed with it): from the installed package
mkdir -p .claude/skills && cp -r vendor/masilia/ibexa-ai-editor/skills/masilia-ibexa-ai-editor .claude/skills/

# For all your projects: global
mkdir -p ~/.claude/skills && cp -r vendor/masilia/ibexa-ai-editor/skills/masilia-ibexa-ai-editor ~/.claude/skills/
```

Restart the agent (or reload its skills) to pick it up. Update the copy after `composer update`.

## The Masilia packages

| Package | What it does |
| --- | --- |
| [`masilia/ibexa-admin-kit`](https://github.com/MasiliaLabs/ibexa-admin-kit) | React components in the design of the Ibexa admin, for admin pages |
| [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) | A registry of sites that Ibexa serves without a deploy |
| [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) | The AI assistant for Ibexa: capabilities, providers, chat, approval flow |
| [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) | The assistant builds sites and pages out of blocks |
| [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) | AI suggestions and image generation in the content editing form |
| [`masilia/ibexa-blocks`](https://github.com/MasiliaLabs/ibexa-blocks) | Front-office rendering of pages made of blocks, with themes |
| [`masilia/editorial-toolbox`](https://github.com/MasiliaLabs/editorial-toolbox) | In-context editing toolbar for the front office |

**What requires what** (Composer installs it for you):

- `ibexa-sites` requires `ibexa-admin-kit`
- `ibexa-ai` requires `ibexa-admin-kit`
- `ibexa-ai-editor` requires `ibexa-ai`
- `ibexa-ai-craft` requires `ibexa-ai`, `ibexa-blocks` and `ibexa-sites`
- `ibexa-blocks` and `editorial-toolbox` require none of the others; they are happy to find `ibexa-sites` (and each other) when installed.

## Compatibility

| Package version | PHP | Ibexa |
| --- | --- | --- |
| `latest` | 8.3+ | 5.0 |

## Download

- **Composer** (recommended): `composer require masilia/ibexa-ai-editor` — [ibexa-ai-editor on Packagist](https://packagist.org/packages/masilia/ibexa-ai-editor)
- **Archive** of a release: [GitHub releases](https://github.com/MasiliaLabs/ibexa-ai-editor/releases) (each release lists what changed)
- **Source**: `git clone https://github.com/MasiliaLabs/ibexa-ai-editor.git`
- What changed between versions: [CHANGELOG.md](CHANGELOG.md)

## Support, contributing and security

- Questions and bug reports: [GitHub issues](https://github.com/MasiliaLabs/ibexa-ai-editor/issues)
- Upgrading across a breaking change: [UPGRADE.md](UPGRADE.md)
- Pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md)
- Security problems: please follow [SECURITY.md](SECURITY.md) and do not open a public issue

## License

Released under the [MIT license](LICENSE). Copyright © 2026 MasiliaLabs.

Ibexa itself is distributed under its own licenses (Ibexa OSS: GPL-2.0), which apply to the project that uses this package. This package is an independent project, not affiliated with or endorsed by Ibexa AS; "Ibexa" is a trademark of its owner.
