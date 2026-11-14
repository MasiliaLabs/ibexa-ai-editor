import { useCallback, useEffect, useReducer, useRef, useState } from 'react';

import { loadLanguages, openSuggestionStream, requestImage } from './api';
import { readSuggestionEvents } from './protocol';
import { browserStore, loadRecent, saveRecent, withRecent } from './recent';
import { canGenerate, canGenerateImage, imageRequestBody, initialState, reducer, requestBody } from './state';
import type { SuggestState } from './state';
import type { ActionId } from './actions';
import type { ApplyMode, ApplyResult, Language, OpenDetail } from './types';

/** How long the "Applied. Undo" bar stays before the modal closes by itself. */
const UNDO_WINDOW_MS = 5000;

export interface SuggestController {
    state: SuggestState;
    languages: Language[];
    recent: string[];
    chooseAction: (id: ActionId) => void;
    setPrompt: (prompt: string) => void;
    setMode: (mode: ApplyMode) => void;
    setSourceLanguage: (language: string) => void;
    setRatio: (ratio: string) => void;
    generate: () => void;
    stop: () => void;
    generateImage: () => void;
    cancelImage: () => void;
    apply: () => void;
    undo: () => void;
    requestClose: () => void;
    confirmClose: () => void;
    cancelClose: () => void;
}

interface Callbacks {
    onApply: OpenDetail['onApply'];
    onUndo?: OpenDetail['onUndo'];
}

/**
 * Orchestrates the modal: listens for the injector's `ai-suggest:open` event, streams suggestions,
 * generates images, applies a result to the form field, and keeps the undo window.
 */
export function useSuggest(): SuggestController {
    const [state, dispatch] = useReducer(reducer, initialState);
    const [languages, setLanguages] = useState<Language[]>([]);
    const [recent, setRecent] = useState<string[]>(() => loadRecent(browserStore()));

    const stateRef = useRef(state);
    stateRef.current = state;
    const recentRef = useRef(recent);
    recentRef.current = recent;
    const callbacks = useRef<Callbacks | null>(null);
    const trigger = useRef<HTMLElement | null>(null);
    const streamAbort = useRef<AbortController | null>(null);
    const imageAbort = useRef<AbortController | null>(null);
    const languagesFor = useRef('');

    const abortAll = useCallback(() => {
        streamAbort.current?.abort();
        streamAbort.current = null;
        imageAbort.current?.abort();
        imageAbort.current = null;
    }, []);

    // The injector opens the modal for a field.
    useEffect(() => {
        const onOpen = (event: Event) => {
            const detail = (event as CustomEvent<OpenDetail>).detail;
            abortAll();
            trigger.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            callbacks.current = { onApply: detail.onApply, onUndo: detail.onUndo };
            dispatch({
                type: 'opened',
                context: {
                    fieldType: detail.fieldType,
                    fieldName: detail.fieldName,
                    subFieldKey: detail.subFieldKey ?? '',
                    metaKeys: detail.metaKeys ?? [],
                    currentValue: detail.currentValue ?? '',
                    contentTypeName: detail.contentTypeName ?? '',
                    language: detail.language ?? '',
                    contentTitle: detail.contentTitle ?? '',
                    siblingFields: detail.siblingFields ?? [],
                    contentId: detail.contentId === undefined || detail.contentId === null ? '' : String(detail.contentId),
                },
                hintAction: detail.hintAction,
                hintSourceLanguage: detail.hintSourceLanguage,
            });
        };

        document.addEventListener('ai-suggest:open', onOpen);

        return () => document.removeEventListener('ai-suggest:open', onOpen);
    }, [abortAll]);

    // Closing stops what is running and gives the focus back to the button that opened the modal.
    useEffect(() => {
        if (!state.open) {
            abortAll();
            trigger.current?.focus();
            trigger.current = null;
        }
    }, [state.open, abortAll]);

    // The "translate from" choice lists the languages of the saved content.
    const contentId = state.context?.contentId ?? '';
    const translating = state.actionId === 'translate';
    useEffect(() => {
        if (!state.open || !translating || contentId === '' || languagesFor.current === contentId) {
            return undefined;
        }
        const controller = new AbortController();
        languagesFor.current = contentId;
        void loadLanguages(contentId, controller.signal).then((list) => {
            if (!controller.signal.aborted) {
                setLanguages(list);
            }
        });

        return () => {
            controller.abort();
            languagesFor.current = '';
        };
    }, [state.open, translating, contentId]);

    // After an apply with an undo, the modal closes by itself.
    useEffect(() => {
        if (state.undo === null) {
            return undefined;
        }
        const timer = window.setTimeout(() => dispatch({ type: 'closed' }), UNDO_WINDOW_MS);

        return () => window.clearTimeout(timer);
    }, [state.undo]);

    const generate = useCallback(() => {
        const current = stateRef.current;
        if (!canGenerate(current)) {
            return;
        }

        streamAbort.current?.abort();
        const controller = new AbortController();
        streamAbort.current = controller;

        if (current.actionId === null) {
            const next = withRecent(recentRef.current, current.prompt);
            setRecent(next);
            saveRecent(browserStore(), next);
        }

        dispatch({ type: 'stream.started' });

        void (async () => {
            let ended = false;
            let status = 0;
            try {
                const response = await openSuggestionStream(requestBody(current), controller.signal);
                status = response.status;
                if (response.body === null) {
                    dispatch({ type: 'stream.failed', message: 'The assistant returned no answer.' });

                    return;
                }

                for await (const event of readSuggestionEvents(response.body)) {
                    if (event.type === 'delta') {
                        dispatch({ type: 'stream.delta', text: event.text });
                    } else if (event.type === 'completed') {
                        ended = true;
                        dispatch({ type: 'stream.completed' });
                    } else {
                        ended = true;
                        dispatch({ type: 'stream.failed', message: event.message });
                    }
                }

                if (!ended) {
                    dispatch({
                        type: 'stream.failed',
                        message: status >= 400
                            ? `The server answered HTTP ${status}.`
                            : 'The connection was interrupted before the suggestion was complete.',
                    });
                }
            } catch (error) {
                if (!controller.signal.aborted) {
                    dispatch({ type: 'stream.failed', message: error instanceof Error ? error.message : 'Network error.' });
                }
            } finally {
                if (streamAbort.current === controller) {
                    streamAbort.current = null;
                }
            }
        })();
    }, []);

    const stop = useCallback(() => {
        streamAbort.current?.abort();
        streamAbort.current = null;
        dispatch({ type: 'stream.stopped' });
    }, []);

    const generateImage = useCallback(() => {
        const current = stateRef.current;
        if (!canGenerateImage(current)) {
            return;
        }

        imageAbort.current?.abort();
        const controller = new AbortController();
        imageAbort.current = controller;
        dispatch({ type: 'image.started' });
        const timer = window.setInterval(() => dispatch({ type: 'image.tick' }), 1000);

        void requestImage(imageRequestBody(current), controller.signal)
            .then((image) => {
                if (!controller.signal.aborted) {
                    dispatch({ type: 'image.ready', image });
                }
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted) {
                    dispatch({ type: 'image.failed', message: error instanceof Error ? error.message : 'Image generation failed.' });
                }
            })
            .finally(() => {
                window.clearInterval(timer);
                if (imageAbort.current === controller) {
                    imageAbort.current = null;
                }
            });
    }, []);

    const cancelImage = useCallback(() => {
        imageAbort.current?.abort();
        imageAbort.current = null;
        dispatch({ type: 'image.cancelled' });
    }, []);

    const apply = useCallback(() => {
        const current = stateRef.current;
        const handlers = callbacks.current;
        if (handlers === null) {
            return;
        }

        let result: ApplyResult | undefined;
        if (current.image.image !== null) {
            result = handlers.onApply(current.image.image, 'image');
        } else if (current.suggestion !== '' && current.status === 'done') {
            result = handlers.onApply(current.suggestion, current.mode);
        } else {
            return;
        }

        if (result !== undefined && result.success === false) {
            dispatch({ type: 'error.set', message: result.error || 'The value could not be applied to the field.' });

            return;
        }

        // An undo needs both a snapshot of what was replaced and a way to restore it.
        const snapshot = result?.success === true && handlers.onUndo !== undefined ? result.snapshot : undefined;
        dispatch({ type: 'applied', snapshot });
    }, []);

    const undo = useCallback(() => {
        const current = stateRef.current;
        if (current.undo !== null) {
            callbacks.current?.onUndo?.(current.undo.snapshot);
        }
        dispatch({ type: 'undone' });
    }, []);

    return {
        state,
        languages,
        recent,
        chooseAction: useCallback((id: ActionId) => dispatch({ type: 'action.chosen', id }), []),
        setPrompt: useCallback((prompt: string) => dispatch({ type: 'prompt.changed', prompt }), []),
        setMode: useCallback((mode: ApplyMode) => dispatch({ type: 'mode.changed', mode }), []),
        setSourceLanguage: useCallback((language: string) => dispatch({ type: 'source.changed', language }), []),
        setRatio: useCallback((ratio: string) => dispatch({ type: 'image.ratio', ratio }), []),
        generate,
        stop,
        generateImage,
        cancelImage,
        apply,
        undo,
        requestClose: useCallback(() => dispatch({ type: 'close.requested' }), []),
        confirmClose: useCallback(() => dispatch({ type: 'closed' }), []),
        cancelClose: useCallback(() => dispatch({ type: 'close.cancelled' }), []),
    };
}
