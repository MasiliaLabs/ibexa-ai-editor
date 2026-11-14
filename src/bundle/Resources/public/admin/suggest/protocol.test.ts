import { test } from 'node:test';
import assert from 'node:assert/strict';

import { readSuggestionEvents, toSuggestionEvent } from './protocol';

test('each suggestion event is read', () => {
    assert.deepEqual(toSuggestionEvent({ event: 'suggestion.delta', data: '{"v":1,"text":"Hi"}' }), { type: 'delta', text: 'Hi' });
    assert.deepEqual(toSuggestionEvent({ event: 'suggestion.completed', data: '{"v":1,"format":"html"}' }), { type: 'completed', format: 'html' });
    assert.deepEqual(toSuggestionEvent({ event: 'suggestion.error', data: '{"v":1,"code":"INVALID_OUTPUT","message":"Bad"}' }), {
        type: 'error',
        code: 'INVALID_OUTPUT',
        message: 'Bad',
    });
});

test('other versions, unknown events and malformed data are ignored', () => {
    assert.equal(toSuggestionEvent({ event: 'suggestion.delta', data: '{"v":2,"text":"Hi"}' }), null);
    assert.equal(toSuggestionEvent({ event: 'suggestion.unknown', data: '{"v":1}' }), null);
    assert.equal(toSuggestionEvent({ event: 'suggestion.delta', data: 'not json' }), null);
    assert.equal(toSuggestionEvent({ event: 'suggestion.delta', data: '{"v":1}' }), null);
});

test('a failed event without details still says something', () => {
    assert.deepEqual(toSuggestionEvent({ event: 'suggestion.error', data: '{"v":1}' }), {
        type: 'error',
        code: 'error',
        message: 'The suggestion failed.',
    });
});

test('a stream with keep-alive comments yields only the real events, accents intact', async () => {
    const encoder = new TextEncoder();
    const body = new ReadableStream<Uint8Array>({
        start(controller) {
            controller.enqueue(encoder.encode(': keep-alive\n\n: keep-alive\n\n'));
            controller.enqueue(encoder.encode('event: suggestion.delta\ndata: {"v":1,"text":"Énergie "}\n\n'));
            controller.enqueue(encoder.encode(': keep-alive\n\nevent: suggestion.delta\ndata: {"v":1,"text":"solaire"}\n\n'));
            controller.enqueue(encoder.encode('event: suggestion.completed\ndata: {"v":1,"format":"text"}\n\n'));
            controller.close();
        },
    });

    const events = [];
    for await (const event of readSuggestionEvents(body)) {
        events.push(event);
    }

    assert.deepEqual(events, [
        { type: 'delta', text: 'Énergie ' },
        { type: 'delta', text: 'solaire' },
        { type: 'completed', format: 'text' },
    ]);
});
