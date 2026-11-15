<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * The brief an image model gets: what the picture is of, what it illustrates, and what it must not be.
 *
 * An image model draws what it is told. A list of form fields ("Content type: …", "Name: …", every field of the item) makes it
 * draw a form, so the context is given as a subject in plain words, with a few lines of the item's own summary, and the
 * rules that keep the result an illustration of it: no text, no screenshot, no interface.
 */
final class ImagePrompt
{
    /** Image models take short prompts (1500 characters for MiniMax): the brief stays well under. */
    public const MAX_CHARS = 1100;
    private const MAX_FACT_CHARS = 280;
    private const MAX_FACTS = 3;

    /** Labels of the fields that sum an item up: they come first. */
    private const SUMMARY_LABEL = '/summar|abstract|intro|lead|teaser|excerpt|standfirst|short.?desc|descri|about|resum|chapeau/i';
    /** Labels of the fields that are not about the subject. */
    private const TECHNICAL_LABEL = '/url|link|slug|seo|meta|keyword|tag|categor|author|date|code|identifier|^id$|image|photo|picture|file|video|alt|caption|position|order|status/i';

    private const RULES = 'Style: a realistic editorial photograph or a clean illustration, well composed, natural light. '
        . 'No text, letters, numbers, logos or watermarks in the picture. '
        . 'It is not a screenshot, a website, an application, a dashboard, a document or a user interface.';

    /**
     * @param list<SiblingField> $siblings The other fields of the item with a value
     */
    public static function build(string $userPrompt, string $contentTypeName, string $title, array $siblings): string
    {
        $userPrompt = self::clean($userPrompt);
        $title = self::clean($title);
        $type = self::clean($contentTypeName);

        $subject = $userPrompt !== ''
            ? $userPrompt
            : ($title !== '' ? sprintf('An image that illustrates “%s”.', $title) : 'An image that illustrates the content of the page.');

        $lines = ['Create an image for a web page.', 'Subject: ' . $subject];

        if ($title !== '' || $type !== '') {
            $lines[] = 'It illustrates ' . ($type !== '' ? 'a ' . mb_strtolower($type) : 'a page') . ($title !== '' ? sprintf(' titled “%s”', $title) : '') . '.';
        }

        $facts = self::facts($siblings);
        if ($facts !== []) {
            $lines[] = 'What it is about (to understand the subject; do not write it in the picture):';
            foreach ($facts as $fact) {
                $lines[] = '- ' . $fact;
            }
        }
        $lines[] = self::RULES;

        $brief = implode("\n", $lines);

        return mb_strlen($brief) <= self::MAX_CHARS ? $brief : self::withoutFacts($lines);
    }

    /**
     * @param list<SiblingField> $siblings
     * @return list<string>
     */
    private static function facts(array $siblings): array
    {
        $summaries = [];
        $others = [];
        foreach ($siblings as $sibling) {
            $value = self::clean($sibling->value);
            if ($value === '' || preg_match(self::TECHNICAL_LABEL, $sibling->label) === 1 && preg_match(self::SUMMARY_LABEL, $sibling->label) !== 1) {
                continue;
            }
            $fact = PromptText::truncate($value, self::MAX_FACT_CHARS);
            if (preg_match(self::SUMMARY_LABEL, $sibling->label) === 1) {
                $summaries[] = $fact;
            } elseif (mb_strlen($value) >= 20) {
                $others[] = $fact;
            }
        }

        return array_slice(array_values(array_unique([...$summaries, ...$others])), 0, self::MAX_FACTS);
    }

    /** @param list<string> $lines */
    private static function withoutFacts(array $lines): string
    {
        $kept = [];
        $skipping = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'What it is about')) {
                $skipping = true;
                continue;
            }
            if ($skipping && str_starts_with($line, '- ')) {
                continue;
            }
            $skipping = false;
            $kept[] = $line;
        }

        return PromptText::truncate(implode("\n", $kept), self::MAX_CHARS);
    }

    /** One line of plain text: tags, line breaks and runs of spaces removed. */
    private static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
