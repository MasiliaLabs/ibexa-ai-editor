<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Ibexa;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\Contracts\IbexaAi\Content\LanguageCode;
use Masilia\IbexaAiEditor\Suggestion\MatrixContext;
use Masilia\IbexaAiEditor\Suggestion\PromptText;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The only place that talks to the Ibexa content API for suggestions.
 *
 * Every read goes through the Repository, so the current user's permissions
 * apply: a content the editor cannot read yields null, not an error.
 */
final readonly class ContentContextReader
{
    public function __construct(
        private ContentService $contentService,
        private FieldValueReader $values,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function load(int $contentId, string $reason): ?Content
    {
        try {
            return $this->contentService->loadContent($contentId);
        } catch (Throwable $e) {
            $this->aiLogger->warning('[AI] Failed to load content {contentId} {reason}: {message}', [
                'contentId' => $contentId,
                'reason' => $reason,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Language codes the content actually has, as stored by Ibexa.
     *
     * @return list<string>
     */
    public function languageCodes(Content $content): array
    {
        return $content->getVersionInfo()->getLanguageCodes();
    }

    /**
     * Resolve the requested language against the content's own languages, or null when it has none.
     */
    public function resolveLanguage(Content $content, string $requested): ?string
    {
        return LanguageCode::resolve($requested, $this->languageCodes($content));
    }

    public function context(Content $content, string $fieldName, string $language): ContentContext
    {
        $contentType = $content->getContentType();
        $fieldIdentifier = $this->resolveFieldIdentifier($fieldName, $contentType);

        [$siblings, $fullText] = $this->siblingsAndFullText($content, $contentType, $fieldIdentifier, $language);

        return new ContentContext(
            title: $content->getName($language) ?? $content->getName() ?? '',
            contentTypeName: (string) $contentType->getName(),
            fieldIdentifier: $fieldIdentifier,
            siblingFields: $siblings,
            matrix: $this->matrix($content, $contentType, $fieldIdentifier, $language),
            contentText: $fullText,
        );
    }

    /**
     * Text of the field in the source language, for translation. Null when the field is empty there.
     */
    public function sourceValue(Content $content, string $fieldIdentifier, string $sourceLanguage): ?string
    {
        $contentType = $content->getContentType();
        $definition = $contentType->getFieldDefinition($fieldIdentifier);
        if ($definition === null) {
            return null;
        }

        $field = $content->getField($fieldIdentifier, $sourceLanguage) ?? $content->getField($fieldIdentifier);
        if ($field === null) {
            return null;
        }

        $text = $this->values->read($field, $definition);

        return $text === '' ? null : PromptText::truncate($text, PromptText::MAX_CURRENT_VALUE_CHARS * 2);
    }

    /**
     * Identifier of the field matching the label shown in the editor, or the slugified name.
     */
    public function resolveFieldIdentifier(string $fieldName, ContentType $contentType): string
    {
        if ($fieldName === '') {
            return '';
        }

        $wanted = mb_strtolower(trim($fieldName));
        foreach ($contentType->getFieldDefinitions() as $definition) {
            if (mb_strtolower(trim((string) $definition->getName())) === $wanted) {
                return $definition->getIdentifier();
            }
        }

        $slug = strtolower(str_replace(' ', '_', $fieldName));
        foreach ($contentType->getFieldDefinitions() as $definition) {
            if ($definition->getIdentifier() === $slug) {
                return $definition->getIdentifier();
            }
        }

        return $slug;
    }

    /**
     * Sibling fields for the prompt (truncated), and the full text of every other field for grounding.
     *
     * @return array{0: list<SiblingField>, 1: string}
     */
    private function siblingsAndFullText(Content $content, ContentType $contentType, string $currentIdentifier, string $language): array
    {
        $siblings = [];
        $fullText = [];
        foreach ($contentType->getFieldDefinitions() as $definition) {
            if ($definition->getIdentifier() === $currentIdentifier) {
                continue;
            }

            $field = $content->getField($definition->getIdentifier(), $language) ?? $content->getField($definition->getIdentifier());
            if ($field === null) {
                continue;
            }

            $text = $this->values->read($field, $definition);
            if ($text === '') {
                continue;
            }

            $fullText[] = $text;
            $siblings[] = new SiblingField(
                label: (string) ($definition->getName() ?: $definition->getIdentifier()),
                value: PromptText::truncate($text, PromptText::MAX_SIBLING_CHARS),
            );
        }

        return [$siblings, implode("\n", $fullText)];
    }

    private function matrix(Content $content, ContentType $contentType, string $identifier, string $language): ?MatrixContext
    {
        $definition = $contentType->getFieldDefinition($identifier);
        if ($definition === null || $definition->getFieldTypeIdentifier() !== 'ibexa_matrix') {
            return null;
        }

        $field = $content->getField($identifier, $language) ?? $content->getField($identifier);

        return $field === null ? null : $this->values->matrixContext($field, $definition);
    }
}
