<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Field types that accept AI suggestions.
 *
 * Canonical values are the Ibexa 5 field type identifiers (checked against the
 * Ibexa 5 vendor; `novaseometas` is the Novactive SEO field type). The legacy
 * `ez*` identifiers used by masilia/ai-assistant are accepted as aliases so the
 * current admin front keeps working during the migration.
 */
enum SupportedField: string
{
    case String = 'ibexa_string';
    case Text = 'ibexa_text';
    case RichText = 'ibexa_richtext';
    case Image = 'ibexa_image';
    case Matrix = 'ibexa_matrix';
    case SeoMetas = 'novaseometas';

    private const LEGACY_IDENTIFIERS = [
        'ezstring' => 'ibexa_string',
        'eztext' => 'ibexa_text',
        'ezrichtext' => 'ibexa_richtext',
        'ezimage' => 'ibexa_image',
        'ezmatrix' => 'ibexa_matrix',
    ];

    public static function fromIdentifier(string $identifier): self
    {
        $canonical = self::LEGACY_IDENTIFIERS[$identifier] ?? $identifier;

        return self::tryFrom($canonical)
            ?? throw new UnsupportedFieldException($identifier);
    }

    /**
     * Default output format. Whole-block SEO output is overridden by
     * {@see SuggestionRequest::outputFormat()}.
     */
    public function format(): FieldFormat
    {
        return match ($this) {
            self::String, self::Image, self::SeoMetas => FieldFormat::PlainText,
            self::Text => FieldFormat::TextBlock,
            self::RichText => FieldFormat::Html,
            self::Matrix => FieldFormat::Json,
        };
    }
}
