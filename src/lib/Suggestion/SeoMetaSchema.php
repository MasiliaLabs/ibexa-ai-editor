<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * The text meta fields a novaseometas field can hold.
 *
 * The bundle supplies the real list from the SEO bundle configuration; the
 * fallback mirrors the conventional keys so prompts stay useful without it.
 */
final readonly class SeoMetaSchema
{
    /**
     * @param array<string, SeoMetaField> $fields keyed by meta key
     */
    public function __construct(
        public array $fields,
    ) {
    }

    public static function fallback(): self
    {
        $fields = [
            new SeoMetaField('title', 'Title'),
            new SeoMetaField('description', 'Description'),
            new SeoMetaField('keywords', 'Keywords'),
            new SeoMetaField('canonical', 'External Canonical URL'),
            new SeoMetaField('type', 'Type'),
            new SeoMetaField('og:title', 'Open Graph Title'),
            new SeoMetaField('og:description', 'Open Graph Description'),
            new SeoMetaField('og:image', 'Open Graph Image URL'),
            new SeoMetaField('og:image:alt', 'Open Graph Image Alt'),
            new SeoMetaField('twitter:title', 'Twitter Title'),
            new SeoMetaField('twitter:description', 'Twitter Description'),
            new SeoMetaField('twitter:image', 'Twitter Image URL'),
        ];

        return new self(array_combine(
            array_map(static fn(SeoMetaField $f) => $f->key, $fields),
            $fields,
        ));
    }

    /**
     * Restrict the schema to the keys present on the form. An empty or
     * non-matching key list keeps the full schema, so the prompt never has
     * an empty JSON schema.
     *
     * @param list<string> $keys
     */
    public function restrictTo(array $keys): self
    {
        if ($keys === []) {
            return $this;
        }

        $wanted = array_map('strtolower', $keys);
        $filtered = array_filter(
            $this->fields,
            static fn(SeoMetaField $field): bool => in_array(strtolower($field->key), $wanted, true),
        );

        return $filtered === [] ? $this : new self($filtered);
    }
}
