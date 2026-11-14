/**
 * Suggestion stream protocol, version 1 (POST /admin/api/ai/suggest/stream).
 *
 *   event: suggestion.delta      data: {"v":1,"text":"…"}
 *   event: suggestion.completed  data: {"v":1,"format":"html"}
 *   event: suggestion.error      data: {"v":1,"code":"…","message":"…"}
 *
 * Unknown events and other versions are ignored, so the server can add events without breaking an open modal.
 */

import { readFrames } from '../shared/sse';
import type { RawFrame } from '../shared/sse';

export const PROTOCOL_VERSION = 1;

export type SuggestionEvent =
    | { type: 'delta'; text: string }
    | { type: 'completed'; format: string }
    | { type: 'error'; code: string; message: string };

export function toSuggestionEvent(frame: RawFrame): SuggestionEvent | null {
    let payload: Record<string, unknown>;
    try {
        const parsed: unknown = JSON.parse(frame.data);
        if (typeof parsed !== 'object' || parsed === null) {
            return null;
        }
        payload = parsed as Record<string, unknown>;
    } catch {
        return null;
    }

    if (payload.v !== PROTOCOL_VERSION) {
        return null;
    }

    switch (frame.event) {
        case 'suggestion.delta':
            return typeof payload.text === 'string' ? { type: 'delta', text: payload.text } : null;
        case 'suggestion.completed':
            return { type: 'completed', format: typeof payload.format === 'string' ? payload.format : 'text' };
        case 'suggestion.error':
            return {
                type: 'error',
                code: typeof payload.code === 'string' ? payload.code : 'error',
                message: typeof payload.message === 'string' ? payload.message : 'The suggestion failed.',
            };
        default:
            return null;
    }
}

export async function* readSuggestionEvents(body: ReadableStream<Uint8Array>): AsyncGenerator<SuggestionEvent> {
    for await (const frame of readFrames(body)) {
        const event = toSuggestionEvent(frame);
        if (event !== null) {
            yield event;
        }
    }
}
