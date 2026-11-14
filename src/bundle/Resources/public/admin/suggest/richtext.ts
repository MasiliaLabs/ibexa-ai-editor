/**
 * Safe reading of the HTML a model writes for a rich text field.
 *
 * The model's output is untrusted (it can be steered by the page content or the prompt), and the preview sits
 * in the admin page. So the HTML is never inserted as markup: it is read into a tree that only holds a short
 * list of known elements and text, and the preview builds React elements from that tree. Scripts, styles,
 * event attributes, unknown elements and every attribute but a safe link target are dropped.
 */

export type AllowedTag =
    | 'p' | 'br' | 'hr' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6'
    | 'ul' | 'ol' | 'li' | 'strong' | 'b' | 'em' | 'i' | 'u' | 's' | 'a'
    | 'blockquote' | 'code' | 'pre' | 'sup' | 'sub'
    | 'table' | 'thead' | 'tbody' | 'tr' | 'th' | 'td';

export type HtmlNode =
    | { type: 'text'; text: string }
    | { type: 'element'; tag: AllowedTag; href?: string; children: HtmlNode[] };

const ALLOWED: ReadonlySet<string> = new Set<AllowedTag>([
    'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'a',
    'blockquote', 'code', 'pre', 'sup', 'sub', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
]);

const VOID: ReadonlySet<string> = new Set(['br', 'hr']);

/** Elements whose content is not text to show: it is dropped with the element. */
const DROP_WITH_CONTENT: ReadonlySet<string> = new Set(['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript']);

// Web addresses, mail and phone links, site paths and anchors. Not javascript:, data:, vbscript: or //host.
const SAFE_HREF = /^(?:https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i;

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', laquo: '«', raquo: '»', hellip: '…', mdash: '—', ndash: '–', rsquo: '’', lsquo: '‘' };

export function decodeEntities(text: string): string {
    return text.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (match, entity: string) => {
        if (entity[0] === '#') {
            const code = entity[1].toLowerCase() === 'x' ? parseInt(entity.slice(2), 16) : parseInt(entity.slice(1), 10);

            return Number.isFinite(code) && code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : match;
        }

        return ENTITIES[entity.toLowerCase()] ?? match;
    });
}

const TOKEN = /<!--[\s\S]*?-->|<\/([a-zA-Z][a-zA-Z0-9-]*)\s*>|<([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>|([^<]+|<)/g;

interface Open {
    tag: string;
    node: Extract<HtmlNode, { type: 'element' }> | null;
    children: HtmlNode[];
}

export function parseSafeHtml(html: string): HtmlNode[] {
    const root: Open = { tag: '', node: null, children: [] };
    const stack: Open[] = [root];
    let skipUntil: string | null = null;

    const top = () => stack[stack.length - 1];

    for (const match of html.matchAll(TOKEN)) {
        const [token, closing, opening, attributes, text] = match;

        if (skipUntil !== null) {
            if (closing !== undefined && closing.toLowerCase() === skipUntil) {
                skipUntil = null;
            }
            continue;
        }

        if (token.startsWith('<!--')) {
            continue;
        }

        if (text !== undefined) {
            appendText(top().children, decodeEntities(text));
            continue;
        }

        if (opening !== undefined) {
            const tag = opening.toLowerCase();

            if (DROP_WITH_CONTENT.has(tag)) {
                if (!token.endsWith('/>')) {
                    skipUntil = tag;
                }
                continue;
            }

            if (!ALLOWED.has(tag)) {
                // Unknown element: its content is kept, the element itself is not.
                continue;
            }

            const node: Extract<HtmlNode, { type: 'element' }> = { type: 'element', tag: tag as AllowedTag, children: [] };
            if (tag === 'a') {
                const href = readHref(attributes ?? '');
                if (href !== null) {
                    node.href = href;
                }
            }
            top().children.push(node);

            if (!VOID.has(tag) && !token.endsWith('/>')) {
                stack.push({ tag, node, children: node.children });
            }
            continue;
        }

        if (closing !== undefined) {
            const tag = closing.toLowerCase();
            // Close up to the matching open element; a closing tag that matches nothing is ignored.
            const index = stack.map((entry) => entry.tag).lastIndexOf(tag);
            if (index > 0) {
                stack.length = index;
            }
        }
    }

    return root.children;
}

function appendText(children: HtmlNode[], text: string): void {
    if (text === '') {
        return;
    }
    const last = children[children.length - 1];
    if (last !== undefined && last.type === 'text') {
        last.text += text;
    } else {
        children.push({ type: 'text', text });
    }
}

function readHref(attributes: string): string | null {
    const match = /(?:^|\s)href\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/i.exec(attributes);
    if (match === null) {
        return null;
    }
    // Entities in an attribute can hide a scheme ("java&#115;cript:"): decode first, then judge.
    const href = decodeEntities(match[1] ?? match[2] ?? match[3] ?? '').trim();

    return SAFE_HREF.test(href) ? href : null;
}
