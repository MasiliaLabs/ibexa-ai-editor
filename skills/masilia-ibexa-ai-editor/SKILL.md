---
name: masilia-ibexa-ai-editor
description: Install and troubleshoot masilia/ibexa-ai-editor, the AI help inside the Ibexa 5 content editing form (a suggestion per field, streamed into a modal, image generation for image fields). Use whenever the user mentions AI suggestions or image generation while editing content in the Ibexa admin, a field-level AI button, suggestion actions/prompts, an image model, or masilia/ibexa-ai-editor, even when the package is not named.
---

# masilia/ibexa-ai-editor

AI help next to each field of the Ibexa content edit form: pick an action (the server applies its own instruction and faithfulness rules) or write a prompt; the text streams into a modal; rich text is sanitised before it reaches the editor. Image fields can generate a picture from a brief built from the content. It uses the providers and rules of `masilia/ibexa-ai` **only through its contracts**.

## Install
`masilia/ibexa-ai` must work first. Then `composer require masilia/ibexa-ai-editor`, enable `MasiliaIbexaAiEditorBundle`, import `@MasiliaIbexaAiEditorBundle/Resources/config/routes.yaml`, `yarn ibexa-generate-tsconfig && yarn encore dev --config-name ibexa` (inside DDEV: `ddev exec`), `cache:clear`.

## Operate
- The provider that answers for the siteaccess in **AI Assistant** is the one used here. Image generation needs a provider with an image model (set it in that page).
- Routes belong to the admin siteaccess group and use the admin CSRF token and Ibexa access checks.

## Troubleshooting
- No AI button in the form: the assets were not rebuilt, the browser holds the old build (hard reload), or the bundle/routes are not enabled.
- "No provider"/empty suggestion: no provider assigned to the siteaccess, or its key cannot be decrypted (see the `masilia-ibexa-ai` skill).
- Image generation fails: the provider has no image model, or the content gives no brief.
- When adding editor behaviour, depend on `Masilia\Contracts\IbexaAi` only, never on `ibexa-ai` internals; run the PHPUnit and Node tests of the package.
