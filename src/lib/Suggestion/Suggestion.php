<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * A cleaned model answer and the format it must be applied with.
 */
final readonly class Suggestion
{
    public function __construct(
        public string $text,
        public FieldFormat $format,
    ) {
    }
}
