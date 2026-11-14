/**
 * Recently used free prompts, kept in the browser. Browser storage can be missing or full (private window,
 * blocked site data), so every access is guarded and the modal works without it.
 */

export const STORAGE_KEY = 'ai-suggest-recent-prompts';
export const MAX_RECENT = 8;

/** The part of the Storage API used here, so tests can pass a plain object. */
export interface KeyValueStore {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
}

export function loadRecent(store: KeyValueStore | null): string[] {
    try {
        const raw = store?.getItem(STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string').slice(0, MAX_RECENT) : [];
    } catch {
        return [];
    }
}

/** The list with `prompt` first and no duplicate; blank prompts are not remembered. */
export function withRecent(list: string[], prompt: string): string[] {
    const text = prompt.trim();
    if (text === '') {
        return list;
    }

    return [text, ...list.filter((item) => item !== text)].slice(0, MAX_RECENT);
}

export function saveRecent(store: KeyValueStore | null, list: string[]): void {
    try {
        store?.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch {
        // Storage full or blocked: the list only lives for this page.
    }
}

/** Storage of this browser, or null when it cannot even be reached. */
export function browserStore(): KeyValueStore | null {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null;
    } catch {
        return null;
    }
}
