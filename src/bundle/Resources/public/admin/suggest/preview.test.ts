import { test } from 'node:test';
import assert from 'node:assert/strict';

import { parseMatrixPreview, parseSeoPreview, stripCodeFence } from './preview';

test('a code fence around an answer is removed', () => {
    assert.equal(stripCodeFence('```json\n{"a":1}\n```'), '{"a":1}');
    assert.equal(stripCodeFence('{"a":1}'), '{"a":1}');
});

test('a matrix keyed by column id becomes a table with its headers', () => {
    const preview = parseMatrixPreview('{"headers":{"name":"Name","role":"Role"},"rows":[{"cells":{"name":"Ada","role":"Chair"}},{"cells":{"name":"Alan"}}]}');

    assert.deepEqual(preview, {
        columns: [{ id: 'name', label: 'Name' }, { id: 'role', label: 'Role' }],
        rows: [['Ada', 'Chair'], ['Alan', '']],
    });
});

test('a matrix written by position uses the position as column id', () => {
    const preview = parseMatrixPreview('{"rows":[{"cells":["a","b"]},{"cells":["c"]}]}');

    assert.deepEqual(preview, {
        columns: [{ id: '0', label: '0' }, { id: '1', label: '1' }],
        rows: [['a', 'b'], ['c', '']],
    });
});

test('anything that is not a rows document is not a matrix preview', () => {
    assert.equal(parseMatrixPreview('not json'), null);
    assert.equal(parseMatrixPreview('{"cells":[]}'), null);
    assert.equal(parseMatrixPreview('[1,2]'), null);
});

test('SEO metadata becomes labelled rows, empty values kept', () => {
    assert.deepEqual(parseSeoPreview('```json\n{"title":"T","og:description":"D","custom":null}\n```'), [
        { key: 'title', label: 'Title', value: 'T' },
        { key: 'og:description', label: 'OG description', value: 'D' },
        { key: 'custom', label: 'custom', value: '' },
    ]);
});

test('invalid or empty SEO metadata is not a preview', () => {
    assert.equal(parseSeoPreview('plain text'), null);
    assert.equal(parseSeoPreview('{}'), null);
    assert.equal(parseSeoPreview('["a"]'), null);
});
