/**
 * State of the suggestion modal. Pure: no React, no network, so every rule can be tested directly.
 *
 * Two ways to ask for text: pick one of the actions (the server applies its own instruction and its
 * faithfulness rules), or write a free prompt. Picking an action clears the prompt, and typing a prompt
 * clears the action, so the two can never disagree.
 */

import { actionById, hasContent, offersImageGeneration, unavailableReason } from './actions';
import type { ActionId } from './actions';
import type { ApplyMode, FieldContext, GeneratedImage } from './types';

export type Status = 'idle' | 'streaming' | 'done' | 'error';
export type ImageStatus = 'idle' | 'loading' | 'ready';

export interface ImageState {
    status: ImageStatus;
    /** Seconds since the request started, for the progress text. */
    elapsed: number;
    ratio: string;
    image: GeneratedImage | null;
}

export interface SuggestState {
    open: boolean;
    context: FieldContext | null;
    actionId: ActionId | null;
    prompt: string;
    mode: ApplyMode;
    sourceLanguage: string;
    status: Status;
    suggestion: string;
    error: string | null;
    image: ImageState;
    /** Set after an apply, so it can be undone. */
    undo: { snapshot: unknown } | null;
    /** The user asked to close while a suggestion was not applied: ask before throwing it away. */
    confirmingClose: boolean;
}

export const IMAGE_RATIOS = ['1:1', '16:9', '9:16', '4:3', '3:4'] as const;

export const initialState: SuggestState = {
    open: false,
    context: null,
    actionId: null,
    prompt: '',
    mode: 'replace',
    sourceLanguage: '',
    status: 'idle',
    suggestion: '',
    error: null,
    image: { status: 'idle', elapsed: 0, ratio: '1:1', image: null },
    undo: null,
    confirmingClose: false,
};

export type Action =
    | { type: 'opened'; context: FieldContext; hintAction?: string; hintSourceLanguage?: string }
    | { type: 'closed' }
    | { type: 'close.requested' }
    | { type: 'close.cancelled' }
    | { type: 'action.chosen'; id: ActionId | null }
    | { type: 'prompt.changed'; prompt: string }
    | { type: 'mode.changed'; mode: ApplyMode }
    | { type: 'source.changed'; language: string }
    | { type: 'stream.started' }
    | { type: 'stream.delta'; text: string }
    | { type: 'stream.completed' }
    | { type: 'stream.failed'; message: string }
    | { type: 'stream.stopped' }
    | { type: 'image.ratio'; ratio: string }
    | { type: 'image.started' }
    | { type: 'image.tick' }
    | { type: 'image.ready'; image: GeneratedImage }
    | { type: 'image.failed'; message: string }
    | { type: 'image.cancelled' }
    | { type: 'error.set'; message: string }
    | { type: 'applied'; snapshot?: unknown }
    | { type: 'undone' };

export function reducer(state: SuggestState, action: Action): SuggestState {
    switch (action.type) {
        case 'opened': {
            const translate = action.hintAction === 'translate' && unavailableReason(actionById('translate')!, action.context) === null;

            return {
                ...initialState,
                open: true,
                context: action.context,
                actionId: translate ? 'translate' : null,
                sourceLanguage: translate ? action.hintSourceLanguage ?? '' : '',
            };
        }

        case 'closed':
            return { ...state, open: false, confirmingClose: false };

        case 'close.requested':
            return hasUnappliedResult(state) ? { ...state, confirmingClose: true } : { ...state, open: false };

        case 'close.cancelled':
            return { ...state, confirmingClose: false };

        case 'action.chosen':
            // Choosing the chosen action again deselects it.
            return {
                ...clearResult(state),
                actionId: action.id === state.actionId ? null : action.id,
                prompt: '',
                sourceLanguage: action.id === 'translate' ? state.sourceLanguage : '',
            };

        case 'prompt.changed':
            return { ...state, prompt: action.prompt, actionId: null, sourceLanguage: '', error: null };

        case 'mode.changed':
            return { ...state, mode: action.mode };

        case 'source.changed':
            return { ...state, sourceLanguage: action.language, error: null };

        case 'stream.started':
            return { ...state, status: 'streaming', suggestion: '', error: null, undo: null, image: { ...state.image, image: null } };

        case 'stream.delta':
            // A model often starts its answer after its reasoning with blank lines: they are never part of the value.
            return state.status === 'streaming'
                ? { ...state, suggestion: state.suggestion === '' ? action.text.trimStart() : state.suggestion + action.text }
                : state;

        case 'stream.completed':
            return state.status === 'streaming' ? { ...state, status: 'done', suggestion: state.suggestion.trimEnd() } : state;

        case 'stream.failed':
            return { ...state, status: 'error', error: action.message };

        case 'stream.stopped':
            // What arrived is kept: the user can still apply a partial result or generate again.
            return state.status === 'streaming' ? { ...state, status: state.suggestion === '' ? 'idle' : 'done', suggestion: state.suggestion.trimEnd() } : state;

        case 'image.ratio':
            return { ...state, image: { ...state.image, ratio: action.ratio } };

        case 'image.started':
            return { ...state, suggestion: '', error: null, undo: null, status: 'idle', image: { ...state.image, status: 'loading', elapsed: 0, image: null } };

        case 'image.tick':
            return state.image.status === 'loading' ? { ...state, image: { ...state.image, elapsed: state.image.elapsed + 1 } } : state;

        case 'image.ready':
            return { ...state, image: { ...state.image, status: 'ready', elapsed: 0, image: action.image } };

        case 'image.failed':
            return { ...state, error: action.message, image: { ...state.image, status: 'idle', elapsed: 0 } };

        case 'image.cancelled':
            return { ...state, image: { ...state.image, status: 'idle', elapsed: 0 } };

        case 'error.set':
            return { ...state, error: action.message };

        case 'applied':
            return {
                ...state,
                undo: action.snapshot === undefined ? null : { snapshot: action.snapshot },
                // Without a snapshot there is nothing to undo: the work is done, the modal can close.
                open: action.snapshot !== undefined,
                confirmingClose: false,
            };

        case 'undone':
            return { ...state, undo: null, open: false };

        default:
            return state;
    }
}

function clearResult(state: SuggestState): SuggestState {
    return {
        ...state,
        status: 'idle',
        suggestion: '',
        error: null,
        undo: null,
        image: { ...state.image, status: state.image.status === 'loading' ? 'loading' : 'idle', image: null },
    };
}

/** A suggestion or generated image that was not applied: closing would lose it. */
export function hasUnappliedResult(state: SuggestState): boolean {
    return state.undo === null && (state.suggestion !== '' || state.image.image !== null);
}

export function isBusy(state: SuggestState): boolean {
    return state.status === 'streaming' || state.image.status === 'loading';
}

/** Why the text suggestion cannot be requested now, or null when it can. Drives the disabled button. */
export function generateBlocker(state: SuggestState): string | null {
    const context = state.context;
    if (context === null) {
        return 'No field.';
    }

    if (state.actionId !== null) {
        const action = actionById(state.actionId);
        const reason = action === null ? null : unavailableReason(action, context);
        if (reason !== null) {
            return reason;
        }
        if (state.actionId === 'translate') {
            if (state.sourceLanguage === '') {
                return 'Choose the language to translate from.';
            }
            if (state.sourceLanguage === context.language) {
                return 'The source language is the language of this version.';
            }
        }

        return null;
    }

    return state.prompt.trim() === '' ? 'Choose an action, or describe what to write.' : null;
}

export function canGenerate(state: SuggestState): boolean {
    return !isBusy(state) && generateBlocker(state) === null;
}

/** The body of POST /api/ai/suggest/stream. */
export function requestBody(state: SuggestState): Record<string, unknown> {
    const context = state.context;
    if (context === null) {
        throw new Error('The modal has no field.');
    }

    return {
        fieldType: context.fieldType,
        action: state.actionId ?? 'custom',
        prompt: state.actionId === null ? state.prompt.trim() : '',
        currentValue: context.currentValue,
        contentType: context.contentTypeName,
        fieldName: context.fieldName,
        language: context.language,
        contentTitle: context.contentTitle,
        siblingFields: context.siblingFields,
        contentId: context.contentId,
        sourceLanguage: state.actionId === 'translate' ? state.sourceLanguage : '',
        subFieldKey: context.subFieldKey,
        metaKeys: context.metaKeys,
    };
}

/** The body of POST /api/ai/generate-image. */
export function imageRequestBody(state: SuggestState): Record<string, unknown> {
    const context = state.context;
    if (context === null) {
        throw new Error('The modal has no field.');
    }

    return {
        // Nothing typed: the server describes the subject from the content itself (its title and what it says).
        prompt: state.prompt.trim(),
        size: state.image.ratio,
        contentId: context.contentId || 0,
        language: context.language,
        fieldName: context.fieldName,
        contentType: context.contentTypeName,
        contentTitle: context.contentTitle,
        siblingFields: context.siblingFields,
    };
}

export function canGenerateImage(state: SuggestState): boolean {
    return state.context !== null && offersImageGeneration(state.context.fieldType) && !isBusy(state);
}

export { hasContent };
