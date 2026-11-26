# Upgrade guide

What to change in your project when you move to a version that breaks something. The full list of changes is in the [CHANGELOG](CHANGELOG.md).

## From 0.2 to 0.3

- Requires `masilia/ibexa-admin-kit` `^0.2` (it was `^0.1.6`). Update the two together: `composer update masilia/ibexa-ai-editor masilia/ibexa-admin-kit`. A project that requires the kit itself has to raise its constraint.
- Works with `masilia/ibexa-ai` `^2.0 || ^3.0`.
- Rebuild the admin after updating (`php bin/console ibexa:encore:compile`) and hard-reload the editing form: the admin scripts changed.

## From 0.1 to 0.2

- Requires `masilia/ibexa-ai` `^2.0`.
- **The admin controllers are Ibexa's**: they check access like the admin, use the admin's CSRF token and their routes belong to the admin siteaccess group. Import the routes from `@MasiliaIbexaAiEditorBundle/Resources/config/routes.yaml` and make sure the editing form is served by an admin siteaccess.
- **The implementation moved to `src/lib`**; the bundle keeps the controllers and the wiring. Update any `use` of classes that held logic.
