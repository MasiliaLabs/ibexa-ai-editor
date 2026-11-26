/**
 * HTTP calls of the suggestion modal. Routes are defined by SuggestionController and ImageController.
 */

import { csrfHeaders, route } from '@masilia-admin-kit/admin';
import type { GeneratedImage, Language } from './types';

/**
 * Starts the suggestion stream. The answer is an event stream even for an error (suggestion.error), so the
 * caller reads it with readSuggestionEvents whatever the status.
 */
export function openSuggestionStream(body: Record<string, unknown>, signal: AbortSignal): Promise<Response> {
    return fetch(route('masilia.ibexa_ai.suggest_stream'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrfHeaders({ 'Content-Type': 'application/json', Accept: 'text/event-stream' }),
        body: JSON.stringify(body),
        signal,
    });
}

export async function requestImage(body: Record<string, unknown>, signal: AbortSignal): Promise<GeneratedImage> {
    const response = await fetch(route('masilia.ibexa_ai.generate_image'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrfHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
        body: JSON.stringify(body),
        signal,
    });

    let data: unknown = null;
    try {
        data = await response.json();
    } catch {
        // Not JSON: handled below with a generic message.
    }

    if (!response.ok) {
        throw new Error(errorMessage(data) ?? `The image could not be generated (HTTP ${response.status}).`);
    }

    const result = data as { imageData?: unknown; mimeType?: unknown } | null;
    if (typeof result?.imageData !== 'string' || result.imageData === '') {
        throw new Error('The image service returned no image.');
    }

    return { imageData: result.imageData, mimeType: typeof result.mimeType === 'string' ? result.mimeType : 'image/png' };
}

/** Languages the content exists in, for the "translate from" choice. Empty when they cannot be loaded. */
export async function loadLanguages(contentId: string, signal: AbortSignal): Promise<Language[]> {
    try {
        const response = await fetch(route('masilia.ibexa_ai.languages', { contentId }), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal,
        });
        if (!response.ok) {
            return [];
        }
        const data = (await response.json()) as { languages?: unknown };

        return Array.isArray(data.languages)
            ? data.languages.filter((item): item is Language => typeof item?.code === 'string' && typeof item?.name === 'string')
            : [];
    } catch {
        return [];
    }
}

function errorMessage(data: unknown): string | null {
    const error = (data as { error?: unknown } | null)?.error;
    if (typeof error === 'string') {
        return error;
    }
    const message = (error as { message?: unknown } | null)?.message;

    return typeof message === 'string' && message !== '' ? message : null;
}
