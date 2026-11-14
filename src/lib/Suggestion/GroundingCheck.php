<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Detects figures in a rewrite that do not appear in the source material.
 *
 * Prompts alone do not stop models from adding statistics, so rewrites are
 * checked: every number in the answer must occur in the text or context it
 * was given. Single digits are ignored (list numbering, ordinal words).
 */
final class GroundingCheck
{
    private const NUMBER = '/\d+(?:[.,]\d+)*%?/';

    /**
     * @return list<string> numbers in $answer that are absent from $source
     */
    public static function ungroundedNumbers(string $answer, string $source): array
    {
        if (preg_match_all(self::NUMBER, $answer, $matches) === false) {
            return [];
        }

        $invented = [];
        foreach ($matches[0] as $number) {
            if (self::isTrivial($number) || str_contains($source, $number)) {
                continue;
            }
            $invented[] = $number;
        }

        return array_values(array_unique($invented));
    }

    private static function isTrivial(string $number): bool
    {
        return strlen($number) === 1;
    }
}
