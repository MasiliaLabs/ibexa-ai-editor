/**
 * Preview data for the structured field types, read from what the model wrote. Pure: the preview component
 * only renders what these return, and falls back to the raw text when they return null.
 */

export interface MatrixPreview {
    columns: Array<{ id: string; label: string }>;
    rows: string[][];
}

export interface SeoPreviewRow {
    key: string;
    label: string;
    value: string;
}

/** Removes a ``` or ```json fence around a model answer, if there is one. */
export function stripCodeFence(text: string): string {
    return text.trim().replace(/^```[a-zA-Z]*\s*/, '').replace(/\s*```$/, '').trim();
}

/**
 * Accepts the two shapes a model may write for a matrix:
 *  {"rows":[{"cells":{"col_a":"v"}}]}   keyed by column id (preferred)
 *  {"rows":[{"cells":["v","v"]}]}        by position
 * `headers` ({colId: "Column name"}) is optional.
 */
export function parseMatrixPreview(text: string): MatrixPreview | null {
    let data: unknown;
    try {
        data = JSON.parse(stripCodeFence(text));
    } catch {
        return null;
    }
    if (typeof data !== 'object' || data === null || !Array.isArray((data as { rows?: unknown }).rows)) {
        return null;
    }

    const { rows, headers } = data as { rows: unknown[]; headers?: Record<string, unknown> };
    const order: string[] = [];
    const remember = (id: string) => {
        if (!order.includes(id)) {
            order.push(id);
        }
    };

    for (const row of rows) {
        const cells = (row as { cells?: unknown } | null)?.cells;
        if (Array.isArray(cells)) {
            cells.forEach((_, index) => remember(String(index)));
        } else if (typeof cells === 'object' && cells !== null) {
            Object.keys(cells).forEach(remember);
        }
    }

    const columns = order.map((id) => ({ id, label: typeof headers?.[id] === 'string' ? (headers[id] as string) : id }));
    const table = rows.map((row) => {
        const cells = (row as { cells?: unknown } | null)?.cells;

        return order.map((id) => {
            const value = Array.isArray(cells) ? cells[Number(id)] : (cells as Record<string, unknown> | undefined)?.[id];

            return value === null || value === undefined ? '' : String(value);
        });
    });

    return { columns, rows: table };
}

const SEO_LABELS: Record<string, string> = {
    title: 'Title',
    description: 'Description',
    keywords: 'Keywords',
    canonical: 'Canonical',
    type: 'Type',
    'og:title': 'OG title',
    'og:description': 'OG description',
    'og:image': 'OG image',
    'twitter:title': 'Twitter title',
    'twitter:description': 'Twitter description',
    'twitter:image': 'Twitter image',
};

/** A flat {"title":"…","description":"…"} object, as the model is asked to write SEO metadata. */
export function parseSeoPreview(text: string): SeoPreviewRow[] | null {
    let data: unknown;
    try {
        data = JSON.parse(stripCodeFence(text));
    } catch {
        return null;
    }
    if (typeof data !== 'object' || data === null || Array.isArray(data)) {
        return null;
    }

    const rows = Object.entries(data as Record<string, unknown>).map(([key, value]) => ({
        key,
        label: SEO_LABELS[key] ?? key,
        value: value === null || value === undefined ? '' : String(value),
    }));

    return rows.length > 0 ? rows : null;
}
