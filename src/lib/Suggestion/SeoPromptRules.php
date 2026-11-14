<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Prompt rules for novaseometas: whole-block JSON output and single-meta text output.
 */
final class SeoPromptRules
{
    private const RECOMMENDED_CHAR_LIMITS = [
        'title' => 60,
        'description' => 160,
    ];

    /** @var array<string, string> exact-match hints for well-known meta keys */
    private const KEY_HINTS = [
        'title' => 'compelling, click-worthy SEO Meta Title',
        'description' => 'concise, engaging Meta Description summarizing the content',
        'keywords' => '5-8 relevant, search-optimized keywords or keyphrases (comma-separated list)',
        'canonical' => 'absolute canonical URL (leave empty if not applicable)',
        'type' => 'content type (e.g. website, article)',
    ];

    /** @var array<string, string> prefix hints for og: and twitter: sub-fields, first match wins */
    private const KEY_HINT_PREFIXES = [
        'og:title' => 'Open Graph title',
        'og:description' => 'Open Graph description',
        'og:image' => 'Open Graph image URL (leave empty if not applicable)',
        'twitter:title' => 'Twitter card title',
        'twitter:description' => 'Twitter card description',
        'twitter:image' => 'Twitter image URL (leave empty if not applicable)',
    ];

    public function wholeBlock(string $base, SeoMetaSchema $schema): string
    {
        $lines = [];
        foreach ($schema->fields as $field) {
            $lines[] = sprintf('  "%s": "%s (%s)"', $field->key, $field->label, $this->hint($field));
        }
        $fields = implode(",\n", $lines);

        return $base . "\n\n"
            . "You are a professional SEO content assistant. Generate SEO metadata for this content item.\n\n"
            . "Output MUST be a valid JSON object matching this schema:\n"
            . "{\n{$fields}\n}\n\n"
            . "Rules:\n"
            . "- Output ONLY the raw JSON object.\n"
            . "- Do NOT wrap the JSON in markdown code blocks (e.g. no ```json).\n"
            . "- Include ONLY the keys listed in the schema above. Do not add extra keys.\n"
            . "- If a field is not applicable to the content (e.g. og:image, twitter:image), set it to an empty string.\n"
            . "- Tailor the values specifically to the content context provided below.";
    }

    public function subField(string $base, string $subFieldKey): string
    {
        $titleLimit = self::RECOMMENDED_CHAR_LIMITS['title'];
        $descriptionLimit = self::RECOMMENDED_CHAR_LIMITS['description'];

        $rules = match ($subFieldKey) {
            'title' => "- Generate a compelling, click-worthy, and SEO-friendly Meta Title.\n- Strictly keep it under {$titleLimit} characters.\n- Primary keywords should be placed near the beginning of the title.\n- Do not repeat the content title identically unless requested.",
            'description' => "- Generate a concise, engaging Meta Description summarizing the content.\n- Keep it under {$descriptionLimit} characters.\n- Include a subtle call-to-action or value proposition.\n- Do not use quotes or special characters.",
            'keywords' => "- Generate a list of 5-8 relevant, search-optimized keywords or keyphrases.\n- Return them ONLY as a comma-separated list (e.g. keyword1, keyword2, keyword3).\n- Do not add any introductory or concluding text, bullet points, or numbering.",
            default => '- Keep the metadata concise, relevant, and search-optimized.',
        };

        return $base . "\n\nRules:\n" . $rules
            . "\n- Output ONLY the generated metadata, nothing else."
            . "\n- Return plain text only — NO JSON, NO markdown code blocks, NO quotes around the value, NO \"value:\" or \"content:\" prefixes."
            . "\n- No HTML tags, no line breaks.";
    }

    private function hint(SeoMetaField $field): string
    {
        $description = self::KEY_HINTS[$field->key] ?? $this->prefixHint($field->key);
        $limit = $this->recommendedLimit($field->key) ?? $field->maxLength;

        return $limit !== null
            ? sprintf('%s, under %d characters', $description, $limit)
            : $description;
    }

    private function recommendedLimit(string $key): ?int
    {
        foreach (self::RECOMMENDED_CHAR_LIMITS as $suffix => $limit) {
            if ($key === $suffix || str_ends_with($key, ':' . $suffix)) {
                return $limit;
            }
        }

        return null;
    }

    private function prefixHint(string $key): string
    {
        foreach (self::KEY_HINT_PREFIXES as $prefix => $hint) {
            if (str_starts_with($key, $prefix)) {
                return $hint;
            }
        }

        return 'metadata value';
    }
}
