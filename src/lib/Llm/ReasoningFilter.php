<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Llm;

/**
 * Removes reasoning blocks ("<think>...</think>") that reasoning models put inline in their answer.
 *
 * {@see strip()} is for complete answers. {@see feed()} / {@see flush()} are for streams, where a tag can
 * be split across chunks: text that might be the start of a tag is held back until the next chunk.
 */
final class ReasoningFilter
{
    private const OPEN = '<think>';
    private const CLOSE = '</think>';

    private bool $inside = false;
    private string $pending = '';

    public static function strip(string $text): string
    {
        $visible = preg_replace('#<think>.*?</think>#s', '', $text) ?? $text;

        // An unclosed block is reasoning that never ended: everything after it is not an answer.
        $open = strpos($visible, self::OPEN);

        return $open === false ? $visible : substr($visible, 0, $open);
    }

    /**
     * Feed one stream chunk; returns the text that is visible to the editor.
     */
    public function feed(string $chunk): string
    {
        $buffer = $this->pending . $chunk;
        $this->pending = '';
        $visible = '';

        while ($buffer !== '') {
            $tag = $this->inside ? self::CLOSE : self::OPEN;
            $position = strpos($buffer, $tag);

            if ($position !== false) {
                if (!$this->inside) {
                    $visible .= substr($buffer, 0, $position);
                }
                $buffer = substr($buffer, $position + strlen($tag));
                $this->inside = !$this->inside;
                continue;
            }

            // No complete tag here: keep a possible partial tag at the end for the next chunk.
            $keep = $this->partialTagLength($buffer, $tag);
            $body = $keep > 0 ? substr($buffer, 0, -$keep) : $buffer;
            $this->pending = $keep > 0 ? substr($buffer, -$keep) : '';

            if (!$this->inside) {
                $visible .= $body;
            }
            break;
        }

        return $visible;
    }

    /**
     * End of stream: whatever was held back is visible text unless it sits inside a reasoning block.
     */
    public function flush(): string
    {
        $rest = $this->inside ? '' : $this->pending;
        $this->pending = '';

        return $rest;
    }

    private function partialTagLength(string $buffer, string $tag): int
    {
        for ($length = min(strlen($tag) - 1, strlen($buffer)); $length > 0; $length--) {
            if (str_ends_with($buffer, substr($tag, 0, $length))) {
                return $length;
            }
        }

        return 0;
    }
}
