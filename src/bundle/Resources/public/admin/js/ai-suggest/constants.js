/**
 * Constants shared by the field injector (the plain-JS side of the suggestion feature).
 */

/**
 * How the AI response is distributed onto the DOM.
 * - WHOLE_BLOCK: parse a JSON object and distribute values to all matching inputs.
 * - SUB_FIELD:   extract one value for a single targeted input.
 */
export const APPLY_MODE = Object.freeze({
    WHOLE_BLOCK: 'whole-block',
    SUB_FIELD: 'sub-field',
});

/** How the generated text is merged with the existing content. */
export const SUGGEST_MODE = Object.freeze({
    REPLACE: 'replace',
    APPEND: 'append',
});

/** The list of field types the server can write suggestions for. */
export const FIELD_TYPES_URL = '/admin/api/ai/field-types';
