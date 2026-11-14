<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Builds the system and user prompts for a suggestion request.
 *
 * Pure string logic: no Ibexa, no Symfony, no HTTP.
 */
final class PromptBuilder
{
    private const BASE = 'You are a professional content writing assistant for a CMS.';

    private readonly SeoMetaSchema $seoSchema;
    private readonly SeoPromptRules $seoRules;

    public function __construct(
        ?SeoMetaSchema $seoSchema = null,
        ?SeoPromptRules $seoRules = null,
    ) {
        $this->seoSchema = $seoSchema ?? SeoMetaSchema::fallback();
        $this->seoRules = $seoRules ?? new SeoPromptRules();
    }

    public function systemPrompt(SuggestionRequest $request): string
    {
        $base = self::BASE . $this->contextLine($request) . $this->contentContext($request) . $this->groundingRules($request);

        return match (true) {
            $request->field === SupportedField::SeoMetas && $request->isWholeBlockSeo()
                => $this->seoRules->wholeBlock($base, $this->seoSchema->restrictTo($request->metaKeys)),
            $request->field === SupportedField::SeoMetas
                => $this->seoRules->subField($base, $request->subFieldKey),
            $request->field === SupportedField::Matrix
                => $base . $this->matrixRules($request->matrix ?? new MatrixContext([], 0)),
            $request->field === SupportedField::Image
                => $base . $this->altTextRules(),
            default
                => $base . $request->field->format()->rules(),
        };
    }

    public function userPrompt(SuggestionRequest $request): string
    {
        if ($request->action === SuggestionAction::Translate) {
            return $request->field === SupportedField::Matrix
                ? $this->translateMatrix($request)
                : $this->translateText($request);
        }

        $instruction = $request->action === SuggestionAction::Custom
            ? trim($request->prompt)
            : (string) $request->action->instruction();

        if ($request->currentValue === '') {
            return $instruction;
        }

        $current = PromptText::truncate($request->currentValue, PromptText::MAX_CURRENT_VALUE_CHARS);

        return "{$instruction}\n\nCurrent content for context (do not repeat it unless asked):\n\"\"\"{$current}\"\"\"";
    }

    /**
     * Rules that keep rewrites grounded in the source text. Free prompts
     * (content creation) and image alt text have no source to stay faithful to.
     */
    private function groundingRules(SuggestionRequest $request): string
    {
        if ($request->action === SuggestionAction::Custom || $request->field === SupportedField::Image) {
            return '';
        }

        $rules = "\n\nFaithfulness rules:"
            . "\n- Do NOT add facts, figures, percentages, dates, names, quotes, sources or claims that are not in the text or the content context."
            . "\n- Do NOT replace the subject or change what the text says; only change how it is written.";

        if ($request->action === SuggestionAction::Lengthen) {
            $rules .= "\n- You may elaborate on what the text already says (explain, clarify, illustrate in general terms), but never add new facts or figures.";
        }

        if ($request->action === SuggestionAction::Translate) {
            $rules .= "\n- Translate exactly what is written: do not add, remove or reorder information.";
        }

        return $rules;
    }

    private function translateText(SuggestionRequest $request): string
    {
        return sprintf(
            "Translate the following %s content to %s. Only output the translated text, "
            . "nothing else. Preserve the tone and style of the original.\n\n%s",
            $request->sourceLanguage,
            $request->language,
            $request->sourceValue,
        );
    }

    private function translateMatrix(SuggestionRequest $request): string
    {
        return sprintf(
            "Translate each cell of the following matrix from %s to %s. "
            . "Output ONLY a JSON object with shape {\"rows\": [{\"cells\": {<col_id>: \"<translated_value>\"}}, ...]}. "
            . "Preserve the original row order. Plain text only in each cell.\n\n%s",
            $request->sourceLanguage,
            $request->language,
            $request->sourceValue,
        );
    }

    private function contextLine(SuggestionRequest $request): string
    {
        $context = '';

        if ($request->contentTypeName !== '') {
            $context .= sprintf(' The content type is "%s".', $request->contentTypeName);
        }
        if ($request->fieldName !== '') {
            $context .= sprintf(' You are writing for the field "%s".', $request->fieldName);
        }
        if (!PromptText::isEnglish($request->language)) {
            $context .= sprintf(' Write in language code: %s.', $request->language);
        }

        return $context;
    }

    private function contentContext(SuggestionRequest $request): string
    {
        $lines = '';

        if ($request->contentTitle !== '') {
            $lines .= sprintf("\nContent title: \"%s\".", PromptText::scrub($request->contentTitle));
        }

        $siblings = '';
        foreach ($request->siblingFields as $sibling) {
            $label = PromptText::scrub($sibling->label);
            $value = PromptText::scrub(mb_substr($sibling->value, 0, PromptText::MAX_SIBLING_CHARS));
            if ($label !== '' && $value !== '') {
                $siblings .= sprintf("\n  - %s: \"%s\"", $label, $value);
            }
        }
        if ($siblings !== '') {
            $lines .= "\nOther fields already filled in this content item (use for context, do not repeat):" . $siblings;
        }

        return $lines === '' ? '' : "\n\nContent context:" . $lines;
    }

    private function matrixRules(MatrixContext $matrix): string
    {
        $columns = '';
        foreach ($matrix->headers as $columnId => $columnName) {
            $columns .= sprintf(
                "\n  - key: \"%s\" (human label: %s)",
                PromptText::scrub((string) $columnId),
                PromptText::scrub((string) $columnName),
            );
        }
        if ($columns === '') {
            $columns = "\n  - (no columns defined; use a single \"value\" key)";
        }

        return "\n\nMatrix generation rules:"
            . "\n- The output is a JSON object with shape: {\"rows\": [{\"cells\": {<col_id>: \"<value>\"}}, ...]}."
            . "\n- Use the EXACT lowercase identifier (the value of the 'key' field below) inside each row's \"cells\" object. Do NOT use the human label, do NOT change case:"
            . $columns
            . sprintf("\n- The output must contain %d row(s) (match the existing row count).", max(1, $matrix->rowCount))
            . "\n- Preserve the original row order."
            . "\n- Each cell value is plain text only. No HTML."
            . "\n- Output ONLY the raw JSON, no markdown code fences, no commentary.";
    }

    private function altTextRules(): string
    {
        return "\n\nAlt text generation rules:"
            . "\n- Generate concise, descriptive alt text for screen readers."
            . sprintf("\n- Keep it under %d characters.", PromptText::MAX_ALT_TEXT_CHARS)
            . "\n- Be specific and factual about the image content."
            . "\n- Do not start with \"Image of\" or \"Photo of\"."
            . "\n- Focus on what the image shows, not its style."
            . "\n- Output ONLY the alt text, no quotes, no commentary."
            . "\n- Plain text only. No HTML.";
    }
}
