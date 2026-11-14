import React from 'react';
import type { ReactNode } from 'react';

import { parseMatrixPreview, parseSeoPreview } from '../preview';
import { parseSafeHtml } from '../richtext';
import type { HtmlNode } from '../richtext';
import type { GeneratedImage } from '../types';

interface PreviewProps {
    fieldType: string;
    text: string;
    image: GeneratedImage | null;
}

/**
 * The suggestion, shown the way it will look. Nothing the model writes is inserted as markup: rich text is read
 * into known elements (see richtext.ts), structured values into tables, everything else is text.
 */
export function Preview({ fieldType, text, image }: PreviewProps) {
    if (image !== null) {
        return <GeneratedImagePreview image={image} />;
    }

    if (fieldType === 'ibexa_richtext') {
        return <div className="masilia-suggest__html">{renderNodes(parseSafeHtml(text))}</div>;
    }

    if (fieldType === 'ibexa_matrix') {
        const matrix = parseMatrixPreview(text);
        if (matrix !== null) {
            return (
                <div className="masilia-suggest__table">
                    <table>
                        {matrix.columns.length > 0 ? (
                            <thead>
                                <tr>
                                    {matrix.columns.map((column) => (
                                        <th key={column.id} scope="col">{column.label}</th>
                                    ))}
                                </tr>
                            </thead>
                        ) : null}
                        <tbody>
                            {matrix.rows.map((row, rowIndex) => (
                                <tr key={rowIndex}>
                                    {row.map((cell, cellIndex) => (
                                        <td key={cellIndex}>{cell === '' ? <span className="masilia-suggest__empty">∅</span> : cell}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
        }
    }

    if (fieldType === 'novaseometas') {
        const seo = parseSeoPreview(text);
        if (seo !== null) {
            return (
                <div className="masilia-suggest__table">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Metadata</th>
                                <th scope="col">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {seo.map((row) => (
                                <tr key={row.key}>
                                    <td>
                                        {row.label}
                                        {row.label !== row.key ? <span className="masilia-suggest__key">{row.key}</span> : null}
                                    </td>
                                    <td>{row.value === '' ? <span className="masilia-suggest__empty">(empty)</span> : row.value}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
        }
    }

    return <div className="masilia-suggest__text">{text}</div>;
}

function renderNodes(nodes: HtmlNode[]): ReactNode[] {
    return nodes.map((node, key) => {
        if (node.type === 'text') {
            return <React.Fragment key={key}>{node.text}</React.Fragment>;
        }

        if (node.tag === 'br' || node.tag === 'hr') {
            return React.createElement(node.tag, { key });
        }

        if (node.tag === 'a') {
            return (
                <a key={key} href={node.href} target="_blank" rel="noopener noreferrer">
                    {renderNodes(node.children)}
                </a>
            );
        }

        return React.createElement(node.tag, { key }, ...renderNodes(node.children));
    });
}

// Only these image types are shown: a data: URL of any other type (text/html, image/svg+xml with script) is not.
const IMAGE_TYPES = /^image\/(png|jpeg|webp|gif)$/;

function GeneratedImagePreview({ image }: { image: GeneratedImage }) {
    const mime = IMAGE_TYPES.test(image.mimeType) ? image.mimeType : 'image/png';
    const base64 = image.imageData.startsWith('data:') ? image.imageData.replace(/^data:[^,]*,/, '') : image.imageData;

    return (
        <div className="masilia-suggest__image">
            <img src={`data:${mime};base64,${base64}`} alt="Generated image, not applied yet" />
        </div>
    );
}
