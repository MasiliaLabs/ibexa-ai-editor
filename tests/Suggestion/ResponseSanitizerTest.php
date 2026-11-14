<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\FieldFormat;
use Masilia\IbexaAiEditor\Suggestion\InvalidModelOutputException;
use Masilia\IbexaAiEditor\Suggestion\ResponseSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseSanitizer::class)]
final class ResponseSanitizerTest extends TestCase
{
    public function testStripsHtmlCodeFence(): void
    {
        self::assertSame('<p>Hello</p>', (new ResponseSanitizer())->clean("```html\n<p>Hello</p>\n```", FieldFormat::Html));
    }

    public function testKeepsHtmlWithoutFenceUntouched(): void
    {
        $raw = '<p>Hello <strong>you</strong></p>';

        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::Html));
    }

    public function testPlainTextIsCollapsedToOneLineAndUnquoted(): void
    {
        self::assertSame(
            'A title spanning lines',
            (new ResponseSanitizer())->clean("\"A title\nspanning lines\"", FieldFormat::PlainText),
        );
    }

    public function testTextBlockKeepsParagraphBreaks(): void
    {
        $raw = "First paragraph.\n\nSecond paragraph.";

        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::TextBlock));
    }

    #[DataProvider('quotePairs')]
    public function testMatchingQuotesAreRemovedOnlyForPlainText(string $open, string $close): void
    {
        $raw = $open . 'quoted' . $close;

        self::assertSame('quoted', (new ResponseSanitizer())->clean($raw, FieldFormat::PlainText));
        self::assertSame($raw, (new ResponseSanitizer())->clean($raw, FieldFormat::TextBlock));
    }

    public static function quotePairs(): iterable
    {
        yield 'double' => ['"', '"'];
        yield 'single' => ["'", "'"];
        yield 'french' => ['«', '»'];
        yield 'curly' => ['“', '”'];
    }

    public function testJsonFenceIsStrippedAndObjectAccepted(): void
    {
        $result = (new ResponseSanitizer())->clean("```json\n{\"rows\": []}\n```", FieldFormat::Json);

        self::assertSame('{"rows": []}', $result);
    }

    #[DataProvider('invalidJson')]
    public function testNonObjectJsonIsRejected(string $raw): void
    {
        $this->expectException(InvalidModelOutputException::class);
        (new ResponseSanitizer())->clean($raw, FieldFormat::Json);
    }

    public static function invalidJson(): iterable
    {
        yield 'prose' => ['Here is your answer: nothing useful'];
        yield 'truncated object' => ['{"rows": ['];
        yield 'list' => ['[1, 2, 3]'];
    }
}
