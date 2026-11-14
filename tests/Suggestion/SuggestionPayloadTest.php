<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\InvalidSuggestionRequestException;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionPayload;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use Masilia\IbexaAiEditor\Suggestion\UnsupportedFieldException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestionPayload::class)]
final class SuggestionPayloadTest extends TestCase
{
    public function testLegacyFrontPayloadIsAFreePromptOnTheLegacyField(): void
    {
        $payload = SuggestionPayload::fromArray([
            'fieldType' => 'ezstring',
            'prompt' => '  Improve the clarity of this text.  ',
            'language' => 'fr',
        ]);

        self::assertSame(SupportedField::String, $payload->field());
        self::assertSame(SuggestionAction::Custom, $payload->action());
        self::assertSame('Improve the clarity of this text.', $payload->prompt);
    }

    public function testSourceLanguageMeansTranslation(): void
    {
        $payload = SuggestionPayload::fromArray([
            'fieldType' => 'ibexa_richtext',
            'prompt' => 'TRANSLATE',
            'sourceLanguage' => 'eng-GB',
            'language' => 'fre-FR',
        ]);

        self::assertSame(SuggestionAction::Translate, $payload->action());
    }

    public function testExplicitActionIdIsUsed(): void
    {
        $payload = SuggestionPayload::fromArray(['fieldType' => 'ibexa_text', 'action' => 'shorten']);

        self::assertSame(SuggestionAction::Shorten, $payload->action());
    }

    public function testUnknownActionIsRejected(): void
    {
        $payload = SuggestionPayload::fromArray(['fieldType' => 'ibexa_text', 'action' => 'teleport']);

        $this->expectException(InvalidSuggestionRequestException::class);
        $payload->action();
    }

    public function testSiblingsWithoutLabelOrValueAreDropped(): void
    {
        $payload = SuggestionPayload::fromArray([
            'fieldType' => 'ezstring',
            'prompt' => 'x',
            'siblingFields' => [
                ['label' => 'Summary', 'value' => 'Text'],
                ['label' => 'Broken'],
                'not an array',
            ],
        ]);

        self::assertEquals([new SiblingField('Summary', 'Text')], $payload->siblingFields);
    }

    public function testMissingFieldTypeIsRejected(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        SuggestionPayload::fromArray(['prompt' => 'x']);
    }

    public function testMissingPromptIsRejectedForFreeActions(): void
    {
        $this->expectException(InvalidSuggestionRequestException::class);
        SuggestionPayload::fromArray(['fieldType' => 'ezstring', 'prompt' => '   ']);
    }

    public function testUnsupportedFieldFailsWhenResolved(): void
    {
        $payload = SuggestionPayload::fromArray(['fieldType' => 'ibexa_keyword', 'prompt' => 'x']);

        $this->expectException(UnsupportedFieldException::class);
        $payload->field();
    }
}
