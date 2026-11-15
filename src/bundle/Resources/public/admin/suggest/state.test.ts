import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    canGenerate,
    canGenerateImage,
    generateBlocker,
    hasUnappliedResult,
    imageRequestBody,
    initialState,
    reducer,
    requestBody,
} from './state';
import type { SuggestState } from './state';
import type { FieldContext } from './types';

function context(overrides: Partial<FieldContext> = {}): FieldContext {
    return {
        fieldType: 'ibexa_string',
        fieldName: 'Title',
        subFieldKey: '',
        metaKeys: [],
        currentValue: 'Some title',
        contentTypeName: 'Article',
        language: 'eng-GB',
        contentTitle: 'Some title',
        siblingFields: [{ label: 'Body', value: 'Text' }],
        contentId: '12',
        ...overrides,
    };
}

function opened(overrides: Partial<FieldContext> = {}, hint: { hintAction?: string; hintSourceLanguage?: string } = {}): SuggestState {
    return reducer(initialState, { type: 'opened', context: context(overrides), ...hint });
}

function streamed(state: SuggestState, ...chunks: string[]): SuggestState {
    let next = reducer(state, { type: 'stream.started' });
    for (const text of chunks) {
        next = reducer(next, { type: 'stream.delta', text });
    }

    return next;
}

test('opening resets everything and shows the field', () => {
    const state = reducer(
        { ...opened(), suggestion: 'old', prompt: 'old prompt', actionId: 'shorten', error: 'old error' },
        { type: 'opened', context: context({ fieldName: 'Intro' }) },
    );

    assert.equal(state.open, true);
    assert.equal(state.context?.fieldName, 'Intro');
    assert.equal(state.suggestion, '');
    assert.equal(state.prompt, '');
    assert.equal(state.actionId, null);
    assert.equal(state.error, null);
});

test('a translate hint opens with translation chosen and the source language filled', () => {
    const state = opened({}, { hintAction: 'translate', hintSourceLanguage: 'fre-FR' });

    assert.equal(state.actionId, 'translate');
    assert.equal(state.sourceLanguage, 'fre-FR');
});

test('a translate hint is ignored when translation is not possible', () => {
    const state = opened({ contentId: '' }, { hintAction: 'translate', hintSourceLanguage: 'fre-FR' });

    assert.equal(state.actionId, null);
    assert.equal(state.sourceLanguage, '');
});

test('choosing an action clears the prompt, and typing a prompt clears the action', () => {
    let state = opened();
    state = reducer(state, { type: 'prompt.changed', prompt: 'Write about solar' });
    state = reducer(state, { type: 'action.chosen', id: 'shorten' });

    assert.equal(state.actionId, 'shorten');
    assert.equal(state.prompt, '');

    state = reducer(state, { type: 'prompt.changed', prompt: 'Something else' });

    assert.equal(state.actionId, null);
    assert.equal(state.prompt, 'Something else');
});

test('choosing the chosen action again deselects it', () => {
    let state = reducer(opened(), { type: 'action.chosen', id: 'improve' });
    state = reducer(state, { type: 'action.chosen', id: 'improve' });

    assert.equal(state.actionId, null);
});

test('an action alone can be generated; a free prompt needs text', () => {
    assert.equal(canGenerate(opened()), false);
    assert.equal(canGenerate(reducer(opened(), { type: 'action.chosen', id: 'improve' })), true);
    assert.equal(canGenerate(reducer(opened(), { type: 'prompt.changed', prompt: '   ' })), false);
    assert.equal(canGenerate(reducer(opened(), { type: 'prompt.changed', prompt: 'Write it' })), true);
});

test('a rewrite on an empty field is blocked, with the reason', () => {
    const state = reducer(opened({ currentValue: '' }), { type: 'action.chosen', id: 'improve' });

    assert.equal(canGenerate(state), false);
    assert.match(generateBlocker(state) ?? '', /empty/);
});

test('translation needs a source language different from the version being edited', () => {
    let state = reducer(opened(), { type: 'action.chosen', id: 'translate' });
    assert.match(generateBlocker(state) ?? '', /Choose the language/);

    state = reducer(state, { type: 'source.changed', language: 'eng-GB' });
    assert.match(generateBlocker(state) ?? '', /same|language of this version/);

    state = reducer(state, { type: 'source.changed', language: 'fre-FR' });
    assert.equal(canGenerate(state), true);
});

test('the request body sends the action id and no prompt for an action', () => {
    const state = reducer(opened(), { type: 'action.chosen', id: 'shorten' });
    const body = requestBody(state);

    assert.equal(body.action, 'shorten');
    assert.equal(body.prompt, '');
    assert.equal(body.sourceLanguage, '');
    assert.equal(body.currentValue, 'Some title');
    assert.deepEqual(body.siblingFields, [{ label: 'Body', value: 'Text' }]);
});

test('the request body sends a free prompt as the custom action', () => {
    const state = reducer(opened(), { type: 'prompt.changed', prompt: '  Write about solar  ' });
    const body = requestBody(state);

    assert.equal(body.action, 'custom');
    assert.equal(body.prompt, 'Write about solar');
});

test('the request body carries the source language only for a translation', () => {
    let state = reducer(opened(), { type: 'action.chosen', id: 'translate' });
    state = reducer(state, { type: 'source.changed', language: 'fre-FR' });

    assert.equal(requestBody(state).sourceLanguage, 'fre-FR');
    assert.equal(requestBody(state).action, 'translate');
});

test('streamed text accumulates, then completes', () => {
    let state = streamed(opened(), 'Hel', 'lo');
    assert.equal(state.status, 'streaming');
    assert.equal(state.suggestion, 'Hello');

    state = reducer(state, { type: 'stream.completed' });
    assert.equal(state.status, 'done');
    assert.equal(hasUnappliedResult(state), true);
});

test('blank lines before and after the answer are not part of the value', () => {
    let state = streamed(opened(), '\n\n', 'Hello', ' world', '\n');
    assert.equal(state.suggestion, 'Hello world\n', 'while streaming only the start is trimmed');

    state = reducer(state, { type: 'stream.completed' });
    assert.equal(state.suggestion, 'Hello world');
});

test('whitespace only at the start of a later chunk is kept: it separates words', () => {
    const state = streamed(opened(), 'Hello', ' world');

    assert.equal(state.suggestion, 'Hello world');
});

test('late text after a stop is ignored, and what arrived is kept', () => {
    let state = streamed(opened(), 'Hello');
    state = reducer(state, { type: 'stream.stopped' });
    state = reducer(state, { type: 'stream.delta', text: ' late' });

    assert.equal(state.suggestion, 'Hello');
    assert.equal(state.status, 'done');
});

test('stopping before any text returns to idle', () => {
    const state = reducer(streamed(opened()), { type: 'stream.stopped' });

    assert.equal(state.status, 'idle');
});

test('a failed stream shows its message and keeps no half result as an error state', () => {
    const state = reducer(streamed(opened(), 'Hel'), { type: 'stream.failed', message: 'The service is down.' });

    assert.equal(state.status, 'error');
    assert.equal(state.error, 'The service is down.');
});

test('generating again clears the previous result and error', () => {
    let state = reducer(streamed(opened(), 'one'), { type: 'stream.failed', message: 'x' });
    state = reducer(state, { type: 'stream.started' });

    assert.equal(state.suggestion, '');
    assert.equal(state.error, null);
    assert.equal(state.status, 'streaming');
});

test('closing asks first when a suggestion was not applied', () => {
    const state = reducer(reducer(streamed(opened(), 'Text'), { type: 'stream.completed' }), { type: 'close.requested' });

    assert.equal(state.open, true);
    assert.equal(state.confirmingClose, true);
    assert.equal(reducer(state, { type: 'close.cancelled' }).confirmingClose, false);
    assert.equal(reducer(state, { type: 'closed' }).open, false);
});

test('closing is immediate when there is nothing to lose', () => {
    assert.equal(reducer(opened(), { type: 'close.requested' }).open, false);
});

test('applying with an undo snapshot keeps the modal open for the undo, then closes it', () => {
    let state = reducer(streamed(opened(), 'Text'), { type: 'stream.completed' });
    state = reducer(state, { type: 'applied', snapshot: { value: 'old' } });

    assert.equal(state.open, true);
    assert.deepEqual(state.undo, { snapshot: { value: 'old' } });
    assert.equal(hasUnappliedResult(state), false, 'an applied result is not lost on close');

    assert.equal(reducer(state, { type: 'undone' }).open, false);
});

test('applying without a snapshot closes the modal', () => {
    const state = reducer(reducer(streamed(opened(), 'Text'), { type: 'stream.completed' }), { type: 'applied' });

    assert.equal(state.open, false);
});

test('image generation is for image fields, and has its own progress and result', () => {
    const image = opened({ fieldType: 'ibexa_image', currentValue: '' });
    assert.equal(canGenerateImage(image), true);
    assert.equal(canGenerateImage(opened()), false);

    let state = reducer(image, { type: 'image.ratio', ratio: '16:9' });
    state = reducer(state, { type: 'image.started' });
    state = reducer(reducer(state, { type: 'image.tick' }), { type: 'image.tick' });
    assert.equal(state.image.status, 'loading');
    assert.equal(state.image.elapsed, 2);
    assert.equal(canGenerateImage(state), false, 'one request at a time');

    state = reducer(state, { type: 'image.ready', image: { imageData: 'AAA', mimeType: 'image/png' } });
    assert.equal(state.image.status, 'ready');
    assert.equal(hasUnappliedResult(state), true);
});

test('the image request sends what was typed (nothing: the server describes the subject), and the chosen ratio', () => {
    const state = reducer(opened({ fieldType: 'ibexa_image', currentValue: '' }), { type: 'image.ratio', ratio: '9:16' });
    const body = imageRequestBody(state);

    assert.equal(body.size, '9:16');
    assert.equal(body.prompt, '');
    assert.equal(imageRequestBody(reducer(state, { type: 'prompt.changed', prompt: 'A wind farm' })).prompt, 'A wind farm');
});

test('a cancelled or failed image request leaves the modal usable', () => {
    let state = reducer(opened({ fieldType: 'ibexa_image' }), { type: 'image.started' });
    state = reducer(state, { type: 'image.failed', message: 'No image model.' });

    assert.equal(state.image.status, 'idle');
    assert.equal(state.error, 'No image model.');
    assert.equal(canGenerateImage(state), true);

    assert.equal(reducer(reducer(opened({ fieldType: 'ibexa_image' }), { type: 'image.started' }), { type: 'image.cancelled' }).image.status, 'idle');
});
