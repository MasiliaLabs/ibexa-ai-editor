<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

final readonly class SeoMetaField
{
    public function __construct(
        public string $key,
        public string $label,
        public ?int $maxLength = null,
    ) {
    }
}
