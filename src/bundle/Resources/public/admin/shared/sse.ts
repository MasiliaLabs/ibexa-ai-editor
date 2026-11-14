/**
 * Server-Sent Events reader shared by the assistant widget and the suggestion modal.
 *
 * A stream is a sequence of frames separated by a blank line. A frame has an optional `event:` line and
 * one or more `data:` lines. Comment lines (starting with ":") and frames without data are ignored, which
 * is how the server keeps a connection busy without telling the client anything.
 */

export interface RawFrame {
    event: string;
    data: string;
}

/**
 * Splits as many complete frames as the buffer holds. The unfinished tail is returned as `rest`,
 * to be prepended to the next chunk.
 */
export function splitFrames(buffer: string): { frames: RawFrame[]; rest: string } {
    const normalized = buffer.replace(/\r\n/g, '\n');
    const pieces = normalized.split('\n\n');
    const rest = pieces.pop() ?? '';
    const frames: RawFrame[] = [];

    for (const piece of pieces) {
        let event = 'message';
        const dataLines: string[] = [];
        for (const line of piece.split('\n')) {
            if (line.startsWith('event:')) {
                event = line.slice('event:'.length).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice('data:'.length).replace(/^ /, ''));
            }
        }
        if (dataLines.length > 0) {
            frames.push({ event, data: dataLines.join('\n') });
        }
    }

    return { frames, rest };
}

/**
 * Reads a response body as a stream of frames. Ends when the body ends. A multi-byte character split
 * across two chunks is rebuilt, not dropped.
 */
export async function* readFrames(body: ReadableStream<Uint8Array>): AsyncGenerator<RawFrame> {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let rest = '';

    try {
        for (;;) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }
            const split = splitFrames(rest + decoder.decode(value, { stream: true }));
            rest = split.rest;
            yield* split.frames;
        }

        // A last frame is complete only if it ends with a blank line; the decoder may still hold a character.
        yield* splitFrames(rest + decoder.decode() + '\n\n').frames;
    } finally {
        reader.releaseLock();
    }
}
