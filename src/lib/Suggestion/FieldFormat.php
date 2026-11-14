<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Output shape the model must produce for a field.
 */
enum FieldFormat: string
{
    case PlainText = 'plain_text';
    case TextBlock = 'text_block';
    case Html = 'html';
    case Json = 'json';

    /**
     * HTML tags the model may emit. Single source of truth for the HTML rules.
     */
    public const HTML_ALLOWED_TAGS = '<p>, <h2>, <h3>, <h4>, <h5>, <h6>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <tr>, <td>, <th>, <thead>, <tbody>, <blockquote>';

    /**
     * Rule block appended to the system prompt for this format.
     */
    public function rules(): string
    {
        $body = match ($this) {
            self::PlainText => "- Output ONLY plain text, single line.\n- No HTML tags, no markdown formatting, no line breaks.\n- Be concise and direct.",
            self::TextBlock => "- Output ONLY plain text.\n- Line breaks are allowed for paragraphs.\n- No HTML tags, no markdown formatting.\n- Write in a clear, structured manner.",
            self::Html => "- Output clean, semantic HTML.\n- Use ONLY these tags: " . self::HTML_ALLOWED_TAGS . ".\n- Do NOT use <h1> (reserved for page title).\n- Do NOT use <div>, classes, IDs, inline styles, or scripts.\n- Do NOT wrap output in ```html code blocks or any markdown.\n- Do NOT include <!DOCTYPE>, <html>, <head>, or <body> tags.\n- Output ONLY the inner HTML content, starting directly with content tags.",
            self::Json => "- Output ONLY a valid raw JSON object.\n- Do NOT wrap the JSON in markdown code blocks.\n- No extra keys, no trailing text.",
        };

        return "\n\nRules:\n" . $body . "\n- Tailor the content specifically to the context provided above.";
    }
}
