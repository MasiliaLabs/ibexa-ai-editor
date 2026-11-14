<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

/**
 * A field of the same content item, used as context for the model.
 */
final readonly class SiblingField
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
