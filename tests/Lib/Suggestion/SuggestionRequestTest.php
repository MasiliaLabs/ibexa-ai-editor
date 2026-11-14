<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\FieldFormat;
use Masilia\IbexaAiEditor\Suggestion\InvalidSuggestionRequestException;
use Masilia\IbexaAiEditor\Suggestion\MatrixContext;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestionRequest::class)]
#[CoversClass(MatrixContext::class)]
final class SuggestionRequestTest extends TestCase
{
    public function testTargetLanguageIsRequired(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Improve, '');
    }

    public function testCustomActionNeedsAPrompt(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Custom, 'en-GB', prompt: '   ');
    }

    public function testTranslationNeedsSourceLanguageAndValue(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new SuggestionRequest(SupportedField::Text, SuggestionAction::Translate, 'fre-FR', sourceLanguage: 'eng-GB');
    }

    public function testWholeBlockSeoOutputsJsonAndSubFieldOutputsText(): void
    {
        $whole = new SuggestionRequest(SupportedField::SeoMetas, SuggestionAction::Improve, 'en-GB');
        $sub = new SuggestionRequest(SupportedField::SeoMetas, SuggestionAction::Improve, 'en-GB', subFieldKey: 'title');

        self::assertSame(FieldFormat::Json, $whole->outputFormat());
        self::assertSame(FieldFormat::PlainText, $sub->outputFormat());
    }

    public function testMatrixOutputsJson(): void
    {
        $request = new SuggestionRequest(
            SupportedField::Matrix,
            SuggestionAction::Improve,
            'en-GB',
            matrix: new MatrixContext(['text' => 'Text'], 2),
        );

        self::assertSame(FieldFormat::Json, $request->outputFormat());
    }

    public function testNegativeMatrixRowCountIsRejected(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        new MatrixContext([], -1);
    }
}
