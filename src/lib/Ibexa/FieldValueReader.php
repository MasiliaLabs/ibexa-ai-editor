<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Ibexa;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\TextBlock\Value as TextBlockValue;
use Ibexa\Core\FieldType\TextLine\Value as TextLineValue;
use Ibexa\FieldTypeMatrix\FieldType\Value as MatrixValue;
use Masilia\IbexaAiEditor\Suggestion\MatrixContext;

/**
 * Reads the editor-facing text of a field value, for sibling context and
 * translation sources. Field types it does not know return an empty string,
 * so they are skipped rather than sent to the model.
 */
final class FieldValueReader
{
    private const MAX_MATRIX_ROWS = 10;

    public function read(Field $field, FieldDefinition $definition): string
    {
        return match ($definition->getFieldTypeIdentifier()) {
            'ibexa_string', 'ibexa_text' => $this->text($field->getValue()),
            'ibexa_richtext' => $this->richText($field->getValue()),
            'ibexa_matrix' => $this->matrix($field->getValue(), $definition),
            default => '',
        };
    }

    /**
     * @return array<string, string> column identifier => display name
     */
    public function matrixHeaders(FieldDefinition $definition): array
    {
        $headers = [];
        foreach (($definition->getFieldSettings()['columns'] ?? []) as $column) {
            if (!isset($column['identifier'])) {
                continue;
            }
            $headers[(string) $column['identifier']] = (string) ($column['name'] ?? $column['identifier']);
        }

        return $headers;
    }

    public function matrixContext(Field $field, FieldDefinition $definition): MatrixContext
    {
        $rowCount = $field->getValue() instanceof MatrixValue ? $field->getValue()->getRows()->count() : 0;

        return new MatrixContext($this->matrixHeaders($definition), $rowCount);
    }

    private function text(mixed $value): string
    {
        if ($value instanceof TextLineValue || $value instanceof TextBlockValue) {
            return trim((string) $value->text);
        }

        return '';
    }

    private function richText(mixed $value): string
    {
        if (is_object($value) && isset($value->xml) && $value->xml instanceof DOMDocument) {
            return trim($value->xml->documentElement?->textContent ?? '');
        }

        return '';
    }

    private function matrix(mixed $value, FieldDefinition $definition): string
    {
        if (!$value instanceof MatrixValue) {
            return '';
        }

        $headers = $this->matrixHeaders($definition);
        $blocks = [];
        $index = 0;

        foreach ($value->getRows() as $row) {
            if (++$index > self::MAX_MATRIX_ROWS) {
                break;
            }

            $cells = [];
            foreach ($row->getCells() as $columnId => $cellValue) {
                $text = trim((string) $cellValue);
                if ($text !== '') {
                    $cells[] = sprintf('  - [%s]: %s', $headers[$columnId] ?? $columnId, $text);
                }
            }

            if ($cells !== []) {
                $blocks[] = sprintf('Row %d:', $index) . "\n" . implode("\n", $cells);
            }
        }

        return implode("\n\n", $blocks);
    }
}
