<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiEditor\Ibexa;

use Masilia\IbexaAiEditor\Suggestion\MatrixContext;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;

/**
 * What the suggestion needs to know about the content item being edited.
 */
final readonly class ContentContext
{
    /**
     * @param list<SiblingField> $siblingFields
     */
    public function __construct(
        public string $title,
        public string $contentTypeName,
        public string $fieldIdentifier,
        public array $siblingFields,
        public ?MatrixContext $matrix,
        public string $contentText = '',
    ) {
    }
}
