<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Validated client payload of POST /admin/api/ai/suggest(/stream).
 *
 * Accepts the payload the current admin front sends (no `action`, the quick
 * action text is sent as `prompt`) and the explicit form (`action` id).
 *
 * Action resolution, in order:
 *  1. a non-empty `sourceLanguage` means translation;
 *  2. a known `action` id is used as-is;
 *  3. otherwise the `prompt` is a free prompt (custom).
 */
final readonly class SuggestionPayload
{
    /**
     * @param list<SiblingField> $siblingFields
     * @param list<string> $metaKeys
     */
    private function __construct(
        public string $fieldType,
        public string $prompt,
        public string $currentValue,
        public string $fieldName,
        public string $language,
        public int $contentId,
        public string $contentTitle,
        public string $contentType,
        public array $siblingFields,
        public string $sourceLanguage,
        public string $subFieldKey,
        public array $metaKeys,
        public ?string $actionId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidSuggestionRequestException
     */
    public static function fromArray(array $data): self
    {
        $fieldType = self::string($data, 'fieldType');
        $prompt = trim(self::string($data, 'prompt'));

        if ($fieldType === '') {
            throw new InvalidSuggestionRequestException('Missing required fields: fieldType, prompt');
        }

        $actionId = self::string($data, 'action') ?: null;
        $sourceLanguage = self::string($data, 'sourceLanguage');
        $isTranslation = $sourceLanguage !== '';

        if (!$isTranslation && $actionId === null && $prompt === '') {
            throw new InvalidSuggestionRequestException('Missing required fields: fieldType, prompt');
        }

        return new self(
            fieldType: $fieldType,
            prompt: $prompt,
            currentValue: self::string($data, 'currentValue'),
            fieldName: self::string($data, 'fieldName'),
            language: self::string($data, 'language') ?: 'en',
            contentId: is_numeric($data['contentId'] ?? null) ? (int) $data['contentId'] : 0,
            contentTitle: self::string($data, 'contentTitle'),
            contentType: self::string($data, 'contentType'),
            siblingFields: self::siblings($data['siblingFields'] ?? []),
            sourceLanguage: $sourceLanguage,
            subFieldKey: trim(self::string($data, 'subFieldKey')),
            metaKeys: array_values(array_filter(
                (array) ($data['metaKeys'] ?? []),
                static fn($key): bool => is_string($key) && $key !== '',
            )),
            actionId: $actionId,
        );
    }

    public function field(): SupportedField
    {
        return SupportedField::fromIdentifier($this->fieldType);
    }

    /**
     * @throws InvalidSuggestionRequestException for an unknown action id or a missing prompt
     */
    public function action(): SuggestionAction
    {
        if ($this->sourceLanguage !== '') {
            return SuggestionAction::Translate;
        }

        if ($this->actionId !== null) {
            return SuggestionAction::tryFrom($this->actionId)
                ?? throw new InvalidSuggestionRequestException(sprintf('Unknown action: %s', $this->actionId));
        }

        return SuggestionAction::Custom;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<SiblingField>
     */
    private static function siblings(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $siblings = [];
        foreach ($raw as $item) {
            if (is_array($item) && is_string($item['label'] ?? null) && is_string($item['value'] ?? null)) {
                $siblings[] = new SiblingField($item['label'], $item['value']);
            }
        }

        return $siblings;
    }
}
