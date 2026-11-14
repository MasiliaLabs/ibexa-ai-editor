<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Ibexa;

use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Core\FieldType\TextBlock\Value as TextBlockValue;
use Ibexa\Core\FieldType\TextLine\Value as TextLineValue;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\FieldTypeMatrix\FieldType\Value as MatrixValue;
use Ibexa\FieldTypeMatrix\FieldType\Value\Row;
use Masilia\IbexaAiEditor\Ibexa\FieldValueReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldValueReader::class)]
final class FieldValueReaderTest extends TestCase
{
    public function testReadsTextLineAndTextBlockValues(): void
    {
        $reader = new FieldValueReader();

        self::assertSame('Hello', $reader->read(
            $this->field(new TextLineValue('  Hello  ')),
            $this->definition('ibexa_string'),
        ));
        self::assertSame('Line one', $reader->read(
            $this->field(new TextBlockValue('Line one')),
            $this->definition('ibexa_text'),
        ));
    }

    public function testReadsRichTextAsPlainText(): void
    {
        $xml = new DOMDocument();
        $xml->loadXML('<section><para>Dune <emphasize>sand</emphasize></para></section>');
        $value = new \stdClass();
        $value->xml = $xml;

        self::assertSame('Dune sand', (new FieldValueReader())->read(
            $this->field($value),
            $this->definition('ibexa_richtext'),
        ));
    }

    public function testMatrixRowsAreLabelledWithColumnNames(): void
    {
        $matrix = new MatrixValue([
            new Row(['text' => 'Buy now', 'url' => '/shop']),
            new Row(['text' => '', 'url' => '']),
        ]);
        $definition = $this->definition('ibexa_matrix', [
            'columns' => [
                ['identifier' => 'text', 'name' => 'Label'],
                ['identifier' => 'url', 'name' => 'Link'],
            ],
        ]);

        $text = (new FieldValueReader())->read($this->field($matrix), $definition);

        self::assertSame("Row 1:\n  - [Label]: Buy now\n  - [Link]: /shop", $text);
    }

    public function testMatrixContextCountsRowsAndReadsColumns(): void
    {
        $matrix = new MatrixValue([new Row(['text' => 'a']), new Row(['text' => 'b'])]);
        $definition = $this->definition('ibexa_matrix', ['columns' => [['identifier' => 'text', 'name' => 'Label']]]);

        $context = (new FieldValueReader())->matrixContext($this->field($matrix), $definition);

        self::assertSame(['text' => 'Label'], $context->headers);
        self::assertSame(2, $context->rowCount);
    }

    public function testUnknownFieldTypesAreSkipped(): void
    {
        self::assertSame('', (new FieldValueReader())->read(
            $this->field(new TextLineValue('ignored')),
            $this->definition('ibexa_keyword'),
        ));
    }

    private function field(mixed $value): Field
    {
        return new Field(['value' => $value, 'fieldDefIdentifier' => 'f', 'languageCode' => 'eng-GB']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function definition(string $fieldType, array $settings = []): FieldDefinition
    {
        return new FieldDefinition([
            'identifier' => 'f',
            'fieldTypeIdentifier' => $fieldType,
            'fieldSettings' => $settings,
        ]);
    }
}
