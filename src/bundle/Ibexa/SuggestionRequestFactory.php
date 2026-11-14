<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Ibexa;

use Masilia\IbexaAiEditor\Suggestion\InvalidSuggestionRequestException;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionPayload;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;

/**
 * Turns a validated client payload into a {@see SuggestionRequest}, enriched
 * with what the content item says. When the content cannot be read, the
 * client's own context is used as is.
 */
final readonly class SuggestionRequestFactory
{
    public function __construct(
        private ContentContextReader $contents,
    ) {
    }

    /**
     * @throws InvalidSuggestionRequestException
     */
    public function build(SuggestionPayload $payload): SuggestionRequest
    {
        $field = $payload->field();
        $action = $payload->action();

        $content = $payload->contentId > 0
            ? $this->contents->load($payload->contentId, 'for suggestion')
            : null;

        if ($content === null) {
            return $this->fromClientContext($payload, $field, $action);
        }

        $language = $this->contents->resolveLanguage($content, $payload->language) ?? $payload->language;
        $context = $this->contents->context($content, $payload->fieldName, $language);

        $sourceLanguage = '';
        $sourceValue = '';
        if ($action === SuggestionAction::Translate) {
            $sourceLanguage = $this->contents->resolveLanguage($content, $payload->sourceLanguage) ?? $payload->sourceLanguage;
            $sourceValue = $this->contents->sourceValue($content, $context->fieldIdentifier, $sourceLanguage) ?? '';
        }

        return new SuggestionRequest(
            field: $field,
            action: $action,
            language: $language,
            prompt: $payload->prompt,
            currentValue: $payload->currentValue,
            fieldName: $payload->fieldName,
            contentTypeName: $context->contentTypeName ?: $payload->contentType,
            contentTitle: $context->title ?: $payload->contentTitle,
            sourceLanguage: $sourceLanguage,
            sourceValue: $sourceValue,
            siblingFields: $context->siblingFields ?: $payload->siblingFields,
            subFieldKey: $payload->subFieldKey,
            metaKeys: $payload->metaKeys,
            matrix: $context->matrix,
            contentText: $context->contentText,
        );
    }

    private function fromClientContext(SuggestionPayload $payload, SupportedField $field, SuggestionAction $action): SuggestionRequest
    {
        if ($action === SuggestionAction::Translate) {
            throw new InvalidSuggestionRequestException('Translation needs an existing content item to read the source from.');
        }

        return new SuggestionRequest(
            field: $field,
            action: $action,
            language: $payload->language,
            prompt: $payload->prompt,
            currentValue: $payload->currentValue,
            fieldName: $payload->fieldName,
            contentTypeName: $payload->contentType,
            contentTitle: $payload->contentTitle,
            siblingFields: $payload->siblingFields,
            subFieldKey: $payload->subFieldKey,
            metaKeys: $payload->metaKeys,
        );
    }
}
