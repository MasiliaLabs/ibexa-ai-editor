<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Ibexa;

/**
 * Matches language codes that may come in different shapes: the admin front
 * sends "fr" or "en", Ibexa stores "fre-FR" or "eng-GB" (ISO 639-2 + region).
 */
final class LanguageCode
{
    /** ISO 639-2 (bibliographic and terminological) => ISO 639-1 */
    private const TO_ISO_639_1 = [
        'eng' => 'en', 'fre' => 'fr', 'fra' => 'fr', 'ger' => 'de', 'deu' => 'de',
        'spa' => 'es', 'ita' => 'it', 'por' => 'pt', 'ara' => 'ar', 'dut' => 'nl',
        'nld' => 'nl', 'rus' => 'ru', 'zho' => 'zh', 'chi' => 'zh', 'jpn' => 'ja',
    ];

    public static function primary(string $code): string
    {
        $primary = strtolower(preg_split('/[-_]/', trim($code))[0] ?? '');

        return self::TO_ISO_639_1[$primary] ?? $primary;
    }

    public static function sameLanguage(string $a, string $b): bool
    {
        return $a !== '' && $b !== '' && self::primary($a) === self::primary($b);
    }

    /**
     * Pick the language among $available that matches $requested: exact code first, then primary language.
     *
     * @param list<string> $available
     */
    public static function resolve(string $requested, array $available): ?string
    {
        if (in_array($requested, $available, true)) {
            return $requested;
        }

        foreach ($available as $code) {
            if (self::sameLanguage($requested, $code)) {
                return $code;
            }
        }

        return null;
    }
}
