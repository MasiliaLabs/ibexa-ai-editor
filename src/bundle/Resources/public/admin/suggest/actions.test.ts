import { test } from 'node:test';
import assert from 'node:assert/strict';

import { ACTIONS, actionById, hasContent, offersImageGeneration, unavailableReason } from './actions';

test('the action ids are exactly the ones the server knows', () => {
    // SuggestionAction on the server: improve, shorten, lengthen, fix_grammar, formal, casual, summarize, translate (custom is a free prompt).
    assert.deepEqual(
        ACTIONS.map((action) => action.id),
        ['improve', 'shorten', 'lengthen', 'fix_grammar', 'formal', 'casual', 'summarize', 'translate'],
    );
});

test('an action is found by id, and an unknown id is no action', () => {
    assert.equal(actionById('shorten')?.label, 'Shorten');
    assert.equal(actionById('nope'), null);
    assert.equal(actionById(null), null);
});

test('plain text is content when it is not blank', () => {
    assert.equal(hasContent('ibexa_string', 'Hello'), true);
    assert.equal(hasContent('ibexa_string', '   '), false);
    assert.equal(hasContent('ibexa_text', ''), false);
});

test('an empty rich text paragraph is empty', () => {
    assert.equal(hasContent('ibexa_richtext', '<p>&nbsp;</p>'), false);
    assert.equal(hasContent('ibexa_richtext', '<p></p><p> </p>'), false);
    assert.equal(hasContent('ibexa_richtext', '<p>Text</p>'), true);
});

test('a matrix of empty cells is empty, one filled cell is content', () => {
    assert.equal(hasContent('ibexa_matrix', '{"rows":[]}'), false);
    assert.equal(hasContent('ibexa_matrix', '{"rows":[{"cells":{"a":"","b":" "}}]}'), false);
    assert.equal(hasContent('ibexa_matrix', '{"rows":[{"cells":{"a":"","b":"x"}}]}'), true);
});

test('SEO metadata with only empty values is empty', () => {
    assert.equal(hasContent('novaseometas', '{"title":"","description":""}'), false);
    assert.equal(hasContent('novaseometas', '{"title":"T","description":""}'), true);
});

test('a rewrite is unavailable on an empty field, with the reason', () => {
    const reason = unavailableReason(actionById('improve')!, { fieldType: 'ibexa_string', currentValue: '', contentId: '12' });

    assert.match(reason ?? '', /empty/);
    assert.equal(unavailableReason(actionById('improve')!, { fieldType: 'ibexa_string', currentValue: 'Text', contentId: '12' }), null);
});

test('translation needs a saved draft, and is not offered for SEO metadata', () => {
    const translate = actionById('translate')!;

    assert.match(unavailableReason(translate, { fieldType: 'ibexa_string', currentValue: '', contentId: '' }) ?? '', /Save the draft/);
    assert.match(unavailableReason(translate, { fieldType: 'novaseometas', currentValue: '{"title":"T"}', contentId: '12' }) ?? '', /SEO/);
    assert.equal(unavailableReason(translate, { fieldType: 'ibexa_string', currentValue: '', contentId: '12' }), null, 'an empty target is the normal case of a translation');
});

test('image generation is offered on image fields only', () => {
    assert.equal(offersImageGeneration('ibexa_image'), true);
    assert.equal(offersImageGeneration('ibexa_string'), false);
    assert.equal(offersImageGeneration('ibexa_richtext'), false);
});
