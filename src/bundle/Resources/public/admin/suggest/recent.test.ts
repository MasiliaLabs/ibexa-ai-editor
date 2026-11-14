import { test } from 'node:test';
import assert from 'node:assert/strict';

import { MAX_RECENT, STORAGE_KEY, loadRecent, saveRecent, withRecent } from './recent';
import type { KeyValueStore } from './recent';

function memory(initial: Record<string, string> = {}): KeyValueStore & { data: Record<string, string> } {
    const data = { ...initial };

    return {
        data,
        getItem: (key) => data[key] ?? null,
        setItem: (key, value) => {
            data[key] = value;
        },
    };
}

test('a new prompt goes first and a repeated one moves up instead of duplicating', () => {
    assert.deepEqual(withRecent(['b', 'a'], 'c'), ['c', 'b', 'a']);
    assert.deepEqual(withRecent(['b', 'a'], 'a'), ['a', 'b']);
    assert.deepEqual(withRecent(['b', 'a'], '  a  '), ['a', 'b']);
});

test('a blank prompt is not remembered', () => {
    assert.deepEqual(withRecent(['a'], '   '), ['a']);
});

test('only the latest prompts are kept', () => {
    const many = Array.from({ length: MAX_RECENT }, (_, index) => `p${index}`);

    assert.equal(withRecent(many, 'new').length, MAX_RECENT);
    assert.equal(withRecent(many, 'new')[0], 'new');
});

test('the list survives a round trip through storage', () => {
    const store = memory();
    saveRecent(store, ['x', 'y']);

    assert.deepEqual(loadRecent(store), ['x', 'y']);
});

test('corrupted, foreign or missing storage gives an empty list, never an error', () => {
    assert.deepEqual(loadRecent(memory({ [STORAGE_KEY]: '{not json' })), []);
    assert.deepEqual(loadRecent(memory({ [STORAGE_KEY]: '{"a":1}' })), []);
    assert.deepEqual(loadRecent(memory({ [STORAGE_KEY]: '["ok", 3, null]' })), ['ok']);
    assert.deepEqual(loadRecent(null), []);
});

test('a storage that refuses writes does not break saving', () => {
    const refusing: KeyValueStore = {
        getItem: () => null,
        setItem: () => {
            throw new Error('QuotaExceededError');
        },
    };

    assert.doesNotThrow(() => saveRecent(refusing, ['a']));
});
