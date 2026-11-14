/**
 * Shapes shared by the suggestion modal. The injector (js/ai-suggest/*, plain JS that finds the fields of the
 * Ibexa edit form and applies values to them) talks to the modal through the `ai-suggest:open` event, whose
 * detail is {@link OpenDetail}.
 */

export interface SiblingField {
    label: string;
    value: string;
}

/** What the modal needs to know about the field it was opened for. */
export interface FieldContext {
    /** ibexa_string, ibexa_text, ibexa_richtext, ibexa_matrix, ibexa_image or novaseometas. */
    fieldType: string;
    fieldName: string;
    /** novaseometas: the meta key when a single meta is targeted, empty otherwise. */
    subFieldKey: string;
    /** novaseometas, whole block: the metas the model may write. */
    metaKeys: string[];
    /** Current value as the injector reads it: text, HTML (rich text), or JSON (matrix, SEO). */
    currentValue: string;
    contentTypeName: string;
    /** Language code of the version being edited. */
    language: string;
    contentTitle: string;
    siblingFields: SiblingField[];
    /** Empty for a draft that was never saved. */
    contentId: string;
}

/** How a suggestion is merged with the existing value. */
export type ApplyMode = 'replace' | 'append';

export interface GeneratedImage {
    /** Base64 data, or a data: URL. */
    imageData: string;
    mimeType: string;
}

export type ApplyResult = { success: true; snapshot?: unknown } | { success: false; error: string };

export interface OpenDetail extends FieldContext {
    /** Writes the value into the form field. The mode is 'image' for a generated image. */
    onApply: (value: string | GeneratedImage, mode: ApplyMode | 'image') => ApplyResult | undefined;
    /** Restores what a previous apply replaced. */
    onUndo?: (snapshot: unknown) => void;
    /** 'translate' opens the modal with the translation action chosen. */
    hintAction?: string;
    hintSourceLanguage?: string;
}

export interface Language {
    code: string;
    name: string;
}
