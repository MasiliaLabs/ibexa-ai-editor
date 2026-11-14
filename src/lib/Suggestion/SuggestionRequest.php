<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Everything the prompt builder needs, already extracted from Ibexa by the
 * bundle layer. Language values are the codes used by the content (e.g. "fre-FR").
 */
final readonly class SuggestionRequest
{
    /**
     * @param list<SiblingField> $siblingFields
     * @param list<string> $metaKeys editable SEO meta keys on the form (novaseometas only)
     */
    public function __construct(
        public SupportedField $field,
        public SuggestionAction $action,
        public string $language,
        public string $prompt = '',
        public string $currentValue = '',
        public string $fieldName = '',
        public string $contentTypeName = '',
        public string $contentTitle = '',
        public string $sourceLanguage = '',
        public string $sourceValue = '',
        public array $siblingFields = [],
        public string $subFieldKey = '',
        public array $metaKeys = [],
        public ?MatrixContext $matrix = null,
        public string $contentText = '',
    ) {
        if ($language === '') {
            throw new InvalidSuggestionRequestException('A target language is required.');
        }
        if ($action === SuggestionAction::Custom && trim($prompt) === '') {
            throw new InvalidSuggestionRequestException('A custom action needs a prompt.');
        }
        if ($action === SuggestionAction::Translate && ($sourceLanguage === '' || trim($sourceValue) === '')) {
            throw new InvalidSuggestionRequestException('Translation needs a source language and a non-empty source value.');
        }
        foreach ($siblingFields as $sibling) {
            if (!$sibling instanceof SiblingField) {
                throw new InvalidSuggestionRequestException('siblingFields must only contain SiblingField objects.');
            }
        }
    }

    /**
     * Whole-block SEO output is a JSON object of meta values; sub-field and
     * other fields keep their default format.
     */
    public function outputFormat(): FieldFormat
    {
        if ($this->isWholeBlockSeo()) {
            return FieldFormat::Json;
        }

        return $this->field->format();
    }

    /**
     * Everything the rewrite may draw on: the text itself, the title and the full text of the
     * content (not the truncated prompt context), so a figure further down the content still counts.
     */
    public function groundingText(): string
    {
        return implode("\n", [$this->currentValue, $this->sourceValue, $this->contentTitle, $this->contentText]);
    }

    /**
     * Free prompts create content and image alt text has no source: neither is checked for grounding.
     */
    public function requiresGrounding(): bool
    {
        return $this->action !== SuggestionAction::Custom && $this->field !== SupportedField::Image;
    }

    public function isWholeBlockSeo(): bool
    {
        return $this->field === SupportedField::SeoMetas && $this->subFieldKey === '';
    }
}
