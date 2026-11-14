<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Text helpers for building prompts from editor content.
 */
final class PromptText
{
    public const MAX_SIBLING_CHARS = 1000;
    public const MAX_CURRENT_VALUE_CHARS = 4000;
    public const MAX_ALT_TEXT_CHARS = 125;

    /**
     * Cut a string to $maxChars characters, appending "..." when truncated.
     */
    public static function truncate(string $value, int $maxChars): string
    {
        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, $maxChars) . '...';
    }

    /**
     * Make a value safe to place inside a quoted prompt line:
     * newlines become spaces and double quotes are escaped.
     */
    public static function scrub(string $value): string
    {
        return str_replace(['"', "\n", "\r"], ['\\"', ' ', ''], $value);
    }

    public static function isEnglish(string $languageCode): bool
    {
        $primary = strtolower(preg_split('/[-_]/', $languageCode)[0] ?? '');

        return in_array($primary, ['en', 'eng'], true);
    }
}
