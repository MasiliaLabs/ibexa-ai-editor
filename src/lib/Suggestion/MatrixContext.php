<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * Column schema and current size of an ezmatrix field, used to constrain the model output.
 */
final readonly class MatrixContext
{
    /**
     * @param array<string, string> $headers column identifier => display name
     */
    public function __construct(
        public array $headers,
        public int $rowCount,
    ) {
        if ($rowCount < 0) {
            throw new InvalidSuggestionRequestException('Matrix row count cannot be negative.');
        }
    }
}
