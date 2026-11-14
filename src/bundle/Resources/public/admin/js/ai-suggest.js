/**
 * AI Content Assistant — Webpack entry point
 *
 * Loaded on Ibexa admin content edit pages.
 * Imports the field injector (plain JS: finds the fields, adds the buttons, applies values) and the React
 * modal (TypeScript: see ../suggest/). They talk through the `ai-suggest:open` event.
 * Styles are loaded separately via ibexa-admin-ui-content-edit-parts-css.
 */
import './ai-suggest-button.js';
import '../suggest/index';
