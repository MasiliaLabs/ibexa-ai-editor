<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Quick actions offered in the editor. Each non-custom action carries the
 * instruction sent to the model; translation has its own prompt builder.
 */
enum SuggestionAction: string
{
    case Improve = 'improve';
    case Shorten = 'shorten';
    case Lengthen = 'lengthen';
    case FixGrammar = 'fix_grammar';
    case Formal = 'formal';
    case Casual = 'casual';
    case Summarize = 'summarize';
    case Translate = 'translate';
    case Custom = 'custom';

    public function instruction(): ?string
    {
        return match ($this) {
            self::Improve => 'Improve the clarity, engagement, and professional tone of this text.',
            self::Shorten => 'Make this text more concise while preserving all key information and meaning.',
            self::Lengthen => 'Expand this text with more detail, examples, and thorough explanations.',
            self::FixGrammar => 'Fix all grammar, spelling, and punctuation errors in this text.',
            self::Formal => 'Rephrase this text in a formal, professional tone.',
            self::Casual => 'Rephrase this text in a casual, conversational tone.',
            self::Summarize => 'Provide a concise summary of this text in 2-3 sentences.',
            self::Translate, self::Custom => null,
        };
    }
}
