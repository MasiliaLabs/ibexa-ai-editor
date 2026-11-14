<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

use Masilia\Contracts\IbexaAi\Llm\ReasoningFilter;
/**
 * Cleans a model answer before it is handed back to the editor.
 *
 * Models often wrap output in code fences or quotes despite instructions.
 * This removes those wrappers without touching the content. JSON output is
 * validated: a non-object answer is an error, not a value to apply.
 */
final class ResponseSanitizer
{
    public function clean(string $raw, FieldFormat $format): string
    {
        $text = $this->stripCodeFence(trim(ReasoningFilter::strip($raw)));

        return match ($format) {
            FieldFormat::Json => $this->validJsonObject($text),
            FieldFormat::PlainText => $this->stripMatchingQuotes(
                trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $text) ?? $text),
            ),
            FieldFormat::TextBlock, FieldFormat::Html => $text,
        };
    }

    private function stripCodeFence(string $text): string
    {
        if (preg_match('/^```[a-zA-Z0-9_-]*\s*\n?(.*?)\n?```$/s', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $text;
    }

    private function validJsonObject(string $text): string
    {
        $decoded = json_decode($text, true);

        if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw new InvalidModelOutputException('The model did not return a JSON object.');
        }

        return $text;
    }

    private function stripMatchingQuotes(string $text): string
    {
        if (mb_strlen($text) >= 2) {
            $first = mb_substr($text, 0, 1);
            $last = mb_substr($text, -1);
            $pairs = ['"' => '"', "'" => "'", '«' => '»', '“' => '”'];
            if (isset($pairs[$first]) && $pairs[$first] === $last) {
                return trim(mb_substr($text, 1, -1));
            }
        }

        return $text;
    }
}
