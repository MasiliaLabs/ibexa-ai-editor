import type { FieldContext } from './types';

/**
 * The one-click actions. Their ids are the ones the server knows (SuggestionAction): it applies its own
 * instruction and its faithfulness rules to each, so the client sends the id, not a prompt.
 */
export type ActionId = 'improve' | 'shorten' | 'lengthen' | 'fix_grammar' | 'formal' | 'casual' | 'summarize' | 'translate';

export interface ActionDef {
    id: ActionId;
    label: string;
    /** What it does, shown when the action is chosen. */
    hint: string;
    /** Icon name in the Design System sprite. */
    icon: string;
}

export const ACTIONS: readonly ActionDef[] = [
    { id: 'improve', label: 'Improve', hint: 'Makes the text clearer and more engaging, without changing what it says.', icon: 'wand' },
    { id: 'shorten', label: 'Shorten', hint: 'Says the same with fewer words.', icon: 'arrow-down-text' },
    { id: 'lengthen', label: 'Lengthen', hint: 'Develops what the text already says. It adds no new facts or figures.', icon: 'arrow-up-text' },
    { id: 'fix_grammar', label: 'Fix grammar', hint: 'Corrects spelling, grammar and punctuation only.', icon: 'edit' },
    { id: 'formal', label: 'Formal', hint: 'Rewrites in a formal, professional tone.', icon: 'business-card' },
    { id: 'casual', label: 'Casual', hint: 'Rewrites in a conversational tone.', icon: 'message-bubble-dots' },
    { id: 'summarize', label: 'Summarize', hint: 'Summarizes the text in two or three sentences.', icon: 'file-text' },
    { id: 'translate', label: 'Translate', hint: 'Translates the saved version of this field from another language.', icon: 'translation-language' },
];

export function actionById(id: string | null): ActionDef | null {
    return ACTIONS.find((action) => action.id === id) ?? null;
}

export const FIELD_LABELS: Record<string, string> = {
    ibexa_string: 'Text line',
    ibexa_text: 'Text block',
    ibexa_richtext: 'Rich text',
    novaseometas: 'SEO metadata',
    ibexa_matrix: 'Matrix',
    ibexa_image: 'Image',
};

/**
 * Whether the field holds anything to work on. Rich text, matrix and SEO values arrive as HTML or JSON,
 * so "not an empty string" is not enough: an empty paragraph or a matrix of empty cells is empty.
 */
export function hasContent(fieldType: string, value: string): boolean {
    const text = value.trim();
    if (text === '') {
        return false;
    }

    if (fieldType === 'ibexa_richtext') {
        return text.replace(/<[^>]*>/g, '').replace(/&nbsp;|&#160;/g, ' ').trim() !== '';
    }

    if (fieldType === 'ibexa_matrix') {
        return jsonHasText(text, (parsed) => (Array.isArray((parsed as { rows?: unknown }).rows) ? (parsed as { rows: unknown[] }).rows : []));
    }

    if (fieldType === 'novaseometas') {
        return jsonHasText(text, (parsed) => Object.values(parsed as Record<string, unknown>));
    }

    return true;
}

function jsonHasText(json: string, parts: (parsed: unknown) => unknown[]): boolean {
    try {
        const parsed: unknown = JSON.parse(json);
        if (typeof parsed !== 'object' || parsed === null) {
            return false;
        }

        return parts(parsed).some(containsText);
    } catch {
        // Not JSON: it is some text.
        return true;
    }
}

function containsText(value: unknown): boolean {
    if (typeof value === 'string') {
        return value.trim() !== '';
    }
    if (Array.isArray(value)) {
        return value.some(containsText);
    }
    if (typeof value === 'object' && value !== null) {
        return Object.values(value).some(containsText);
    }

    return false;
}

/**
 * Why an action cannot run now, or null when it can. The reason is shown to the user instead of letting
 * the model be asked to rewrite nothing (it would invent text).
 */
export function unavailableReason(action: ActionDef, context: Pick<FieldContext, 'fieldType' | 'currentValue' | 'contentId'>): string | null {
    if (action.id === 'translate') {
        if (context.fieldType === 'novaseometas') {
            return 'Translation is not available for SEO metadata.';
        }
        if (context.contentId === '') {
            return 'Save the draft first: the text to translate is read from the saved content.';
        }

        return null;
    }

    if (!hasContent(context.fieldType, context.currentValue)) {
        return 'This field is empty, so there is nothing to rewrite. Describe what to write instead.';
    }

    return null;
}

/** Image generation is offered on image fields only: it is the only field it can be applied to. */
export function offersImageGeneration(fieldType: string): boolean {
    return fieldType === 'ibexa_image';
}
