<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\FieldFormat;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use Masilia\IbexaAiEditor\Suggestion\UnsupportedFieldException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupportedField::class)]
final class SupportedFieldTest extends TestCase
{
    #[DataProvider('identifiers')]
    public function testCanonicalAndLegacyIdentifiersResolveToTheSameField(string $identifier, SupportedField $expected): void
    {
        self::assertSame($expected, SupportedField::fromIdentifier($identifier));
    }

    public static function identifiers(): iterable
    {
        yield 'ibexa string' => ['ibexa_string', SupportedField::String];
        yield 'legacy string' => ['ezstring', SupportedField::String];
        yield 'legacy text' => ['eztext', SupportedField::Text];
        yield 'ibexa richtext' => ['ibexa_richtext', SupportedField::RichText];
        yield 'legacy richtext' => ['ezrichtext', SupportedField::RichText];
        yield 'legacy image' => ['ezimage', SupportedField::Image];
        yield 'ibexa matrix' => ['ibexa_matrix', SupportedField::Matrix];
        yield 'legacy matrix' => ['ezmatrix', SupportedField::Matrix];
        yield 'seo metas' => ['novaseometas', SupportedField::SeoMetas];
    }

    #[DataProvider('unsupportedIdentifiers')]
    public function testUnknownFieldTypesAreRejected(string $identifier): void
    {
        $this->expectException(UnsupportedFieldException::class);
        SupportedField::fromIdentifier($identifier);
    }

    public static function unsupportedIdentifiers(): iterable
    {
        yield 'keyword' => ['ibexa_keyword'];
        yield 'empty' => [''];
    }

    public function testDefaultFormats(): void
    {
        self::assertSame(FieldFormat::Json, SupportedField::Matrix->format());
        self::assertSame(FieldFormat::Html, SupportedField::RichText->format());
        self::assertSame(FieldFormat::PlainText, SupportedField::SeoMetas->format());
    }
}
