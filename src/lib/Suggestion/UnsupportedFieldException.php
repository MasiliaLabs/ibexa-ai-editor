<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

final class UnsupportedFieldException extends InvalidSuggestionRequestException
{
    public function __construct(
        public readonly string $fieldType,
    ) {
        parent::__construct(sprintf('Unsupported field type: %s', $fieldType));
    }
}
