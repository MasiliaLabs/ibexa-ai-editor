import { test } from 'node:test';
import assert from 'node:assert/strict';

import { readFrames, splitFrames } from './sse';

function bodyOf(...chunks: Array<string | Uint8Array>): ReadableStream<Uint8Array> {
    const encoder = new TextEncoder();

    return new ReadableStream<Uint8Array>({
        start(controller) {
            for (const chunk of chunks) {
                controller.enqueue(typeof chunk === 'string' ? encoder.encode(chunk) : chunk);
            }
            controller.close();
        },
    });
}

async function collect(body: ReadableStream<Uint8Array>) {
    const frames = [];
    for await (const frame of readFrames(body)) {
        frames.push(frame);
    }

    return frames;
}

test('frames carry their event name, or "message" when there is none', () => {
    const { frames } = splitFrames('event: suggestion.delta\ndata: {"v":1}\n\ndata: plain\n\n');

    assert.deepEqual(frames, [
        { event: 'suggestion.delta', data: '{"v":1}' },
        { event: 'message', data: 'plain' },
    ]);
});

test('comment frames and frames without data are ignored', () => {
    const { frames } = splitFrames(': keep-alive\n\nevent: ping\n\nevent: x\ndata: ok\n\n');

    assert.deepEqual(frames, [{ event: 'x', data: 'ok' }]);
});

test('several data lines of one frame are joined with a newline', () => {
    const { frames } = splitFrames('data: one\ndata: two\n\n');

    assert.deepEqual(frames, [{ event: 'message', data: 'one\ntwo' }]);
});

test('a frame split across chunks is rebuilt', async () => {
    const frames = await collect(bodyOf('event: a\ndata: {"x":', '1}\n\nevent: b\nda', 'ta: 2\n\n'));

    assert.deepEqual(frames, [
        { event: 'a', data: '{"x":1}' },
        { event: 'b', data: '2' },
    ]);
});

test('a multi-byte character cut between two chunks is kept', async () => {
    const bytes = new TextEncoder().encode('event: a\ndata: é\n\n');
    const cut = bytes.indexOf(0xc3) + 1;

    const frames = await collect(bodyOf(bytes.slice(0, cut), bytes.slice(cut)));

    assert.deepEqual(frames, [{ event: 'a', data: 'é' }]);
});

test('a last frame without its closing blank line is still read', async () => {
    const frames = await collect(bodyOf('event: a\ndata: end'));

    assert.deepEqual(frames, [{ event: 'a', data: 'end' }]);
});
