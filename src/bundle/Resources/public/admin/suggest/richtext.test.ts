import { test } from 'node:test';
import assert from 'node:assert/strict';

import { decodeEntities, parseSafeHtml } from './richtext';
import type { HtmlNode } from './richtext';

/** The tree as a compact string, to keep expectations readable: <p>text</p>, <a[href]>…</a>. */
function show(nodes: HtmlNode[]): string {
    return nodes
        .map((node) => {
            if (node.type === 'text') {
                return node.text;
            }
            const href = node.href !== undefined ? `[${node.href}]` : '';

            return node.children.length === 0 && (node.tag === 'br' || node.tag === 'hr')
                ? `<${node.tag}/>`
                : `<${node.tag}${href}>${show(node.children)}</${node.tag}>`;
        })
        .join('');
}

test('paragraphs, lists and inline formatting are kept', () => {
    assert.equal(
        show(parseSafeHtml('<p>Hello <strong>bold</strong> and <em>soft</em></p><ul><li>one</li><li>two</li></ul>')),
        '<p>Hello <strong>bold</strong> and <em>soft</em></p><ul><li>one</li><li>two</li></ul>',
    );
});

test('a script element is dropped with its content', () => {
    assert.equal(show(parseSafeHtml('<p>Safe</p><script>alert(document.cookie)</script><p>After</p>')), '<p>Safe</p><p>After</p>');
});

test('style, iframe and object are dropped with their content', () => {
    assert.equal(show(parseSafeHtml('a<style>body{display:none}</style>b<iframe src="https://evil.example">x</iframe>c')), 'abc');
});

test('event handler attributes never survive', () => {
    assert.equal(show(parseSafeHtml('<p onclick="steal()" style="x" class="y">Text</p>')), '<p>Text</p>');
    assert.equal(show(parseSafeHtml('<img src=x onerror="alert(1)">After')), 'After');
});

test('a link keeps only a safe target', () => {
    assert.equal(show(parseSafeHtml('<a href="https://example.org/a">site</a>')), '<a[https://example.org/a]>site</a>');
    assert.equal(show(parseSafeHtml('<a href="/admin/x">path</a>')), '<a[/admin/x]>path</a>');
    assert.equal(show(parseSafeHtml('<a href="mailto:a@example.org">mail</a>')), '<a[mailto:a@example.org]>mail</a>');
});

test('javascript:, data: and protocol-relative targets are removed', () => {
    for (const href of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example/x']) {
        assert.equal(show(parseSafeHtml(`<a href="${href}">x</a>`)), '<a>x</a>', href);
    }
});

test('a scheme hidden behind entities or whitespace is still caught', () => {
    assert.equal(show(parseSafeHtml('<a href="java&#115;cript:alert(1)">x</a>')), '<a>x</a>');
    assert.equal(show(parseSafeHtml('<a href="  javascript:alert(1)">x</a>')), '<a>x</a>');
    assert.equal(show(parseSafeHtml('<a href="&#106;avascript:alert(1)">x</a>')), '<a>x</a>');
});

test('unknown elements are unwrapped: their text stays, the element does not', () => {
    assert.equal(show(parseSafeHtml('<div><custom-tag>kept</custom-tag></div>')), 'kept');
});

test('comments are dropped and a lone < is text', () => {
    assert.equal(show(parseSafeHtml('a<!-- <script>x</script> -->b')), 'ab');
    assert.equal(show(parseSafeHtml('1 < 2')), '1 < 2');
});

test('markup written as text stays text: it is never re-read as markup', () => {
    // The text node holds the characters; the preview renders it as text, so no element is created.
    assert.equal(show(parseSafeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')), '<script>alert(1)</script>');
    const nodes = parseSafeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
    assert.equal(nodes.length, 1);
    assert.equal(nodes[0].type, 'text');
});

test('unclosed and misnested elements are repaired', () => {
    assert.equal(show(parseSafeHtml('<p>one <strong>two</p><p>three')), '<p>one <strong>two</strong></p><p>three</p>');
    assert.equal(show(parseSafeHtml('text</p></div>more')), 'textmore');
});

test('void elements and self-closing forms do not swallow what follows', () => {
    assert.equal(show(parseSafeHtml('<p>a<br>b<br/>c</p><hr>d')), '<p>a<br/>b<br/>c</p><hr/>d');
});

test('tables keep their structure', () => {
    assert.equal(
        show(parseSafeHtml('<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>')),
        '<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>',
    );
});

test('entities are decoded into text', () => {
    assert.equal(decodeEntities('Fish &amp; chips &lt;3 &#233;&#xe9; &nbsp;&hellip;'), 'Fish & chips <3 éé  …');
    assert.equal(decodeEntities('&unknown; &#0; &#99999999;'), '&unknown; &#0; &#99999999;');
});

test('a hostile document produces only known elements and text', () => {
    const hostile = [
        '<p>ok</p>',
        '<svg onload="alert(1)"><circle/></svg>',
        '<img src="x" onerror="alert(2)">',
        '<a href="javascript:alert(3)" onclick="alert(4)">link</a>',
        '<form action="https://evil.example"><input name="p"></form>',
        '<script>alert(5)</script>',
        '<math><mi xlink:href="javascript:alert(6)">x</mi></math>',
    ].join('');
    const allowed = new Set(['p', 'a']);

    const walk = (nodes: HtmlNode[]): void => {
        for (const node of nodes) {
            if (node.type === 'element') {
                assert.ok(allowed.has(node.tag), `unexpected element ${node.tag}`);
                assert.equal(node.href, undefined);
                walk(node.children);
            }
        }
    };
    walk(parseSafeHtml(hostile));
});

test('random assemblies of hostile fragments never produce an unknown element or an unsafe link', () => {
    const fragments = [
        '<p>', '</p>', '<a href="javascript:x">', '<a href="https://ok.example">', '</a>', '<script>', '</script>', '<style>',
        '</style>', '<img src=x onerror=y>', '<svg/onload=z>', '<b>', '</b>', '<li>', '<ul>', '</ul>', '<!--', '-->', '<', '>',
        '&lt;', '&#106;avascript:', 'text', ' ', '"', "'", '<iframe>', '<td>', '<table>', '<br>', '</div>', '<div class="x">',
        '<a href=&#106;avascript:q>', '<A HREF="JAVASCRIPT:w">', '<<', '>>', '\u0000', 'é',
    ];
    const allowed = new Set(['p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'blockquote', 'code', 'pre', 'sup', 'sub', 'table', 'thead', 'tbody', 'tr', 'th', 'td']);
    const safe = /^(?:https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i;
    let seed = 12345;
    const next = () => {
        seed = (seed * 1103515245 + 12345) & 0x7fffffff;

        return seed;
    };

    const walk = (nodes: HtmlNode[]): void => {
        for (const node of nodes) {
            if (node.type === 'element') {
                assert.ok(allowed.has(node.tag), `unknown element ${node.tag}`);
                if (node.href !== undefined) {
                    assert.match(node.href, safe);
                }
                walk(node.children);
            }
        }
    };

    for (let run = 0; run < 2000; run++) {
        const length = 1 + (next() % 14);
        let html = '';
        for (let i = 0; i < length; i++) {
            html += fragments[next() % fragments.length];
        }
        walk(parseSafeHtml(html));
    }
});
