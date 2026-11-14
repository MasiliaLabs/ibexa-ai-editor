<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\FieldFormat;
use Masilia\IbexaAiEditor\Suggestion\InvalidModelOutputException;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SuggestionService;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestionService::class)]
final class SuggestionServiceTest extends TestCase
{
    public function testSuggestSendsBothPromptsAndSanitisesTheAnswer(): void
    {
        $generator = new FakeTextGenerator(answer: "```html\n<p>Improved</p>\n```");
        $service = new SuggestionService($generator);

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            currentValue: '<p>Old</p>',
        ));

        self::assertSame('<p>Improved</p>', $result->text);
        self::assertSame(FieldFormat::Html, $result->format);
        self::assertStringContainsString('Do NOT use <h1>', $generator->calls[0]['system']);
        self::assertStringContainsString('<p>Old</p>', $generator->calls[0]['user']);
    }

    public function testTranslationGoesThroughTheSameServiceWithSourceText(): void
    {
        $generator = new FakeTextGenerator(answer: 'Bonjour');
        $service = new SuggestionService($generator);

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::String,
            action: SuggestionAction::Translate,
            language: 'fre-FR',
            sourceLanguage: 'eng-GB',
            sourceValue: 'Hello',
        ));

        self::assertSame('Bonjour', $result->text);
        self::assertStringContainsString('Translate the following eng-GB content to fre-FR.', $generator->calls[0]['user']);
    }

    public function testWholeBlockSeoAnswerMustBeAnObject(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(answer: 'Sorry, I cannot do that.'));

        $this->expectException(InvalidModelOutputException::class);
        $service->suggest(new SuggestionRequest(SupportedField::SeoMetas, SuggestionAction::Improve, 'en-GB'));
    }

    public function testStreamYieldsChunksInOrder(): void
    {
        $generator = new FakeTextGenerator(chunks: ['Hel', 'lo', ' world']);
        $service = new SuggestionService($generator);

        $chunks = iterator_to_array($service->stream(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Summarize,
            language: 'en-GB',
            currentValue: 'Some text.',
        )), false);

        self::assertSame(['Hel', 'lo', ' world'], $chunks);
        self::assertCount(1, $generator->calls);
    }

    public function testReasoningIsReportedAsActivityWithoutVisibleText(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(chunks: ['<think>planning ', 'the answer', '</think>', 'Hello']));

        $events = iterator_to_array($service->streamWithActivity(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Summarize,
            language: 'en-GB',
            currentValue: 'Some text.',
        )), false);

        // One entry per model chunk, so a caller can keep the connection busy; null means nothing visible.
        self::assertSame([null, null, null, 'Hello'], $events);
    }

    public function testStreamNeverYieldsTheActivityMarkers(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(chunks: ['<think>hmm</think>', 'Hi']));

        $chunks = iterator_to_array($service->stream(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Summarize,
            language: 'en-GB',
            currentValue: 'Some text.',
        )), false);

        self::assertSame(['Hi'], $chunks);
    }

    public function testInventedFiguresTriggerOneRetryWithAGroundedAnswer(): void
    {
        $generator = new FakeTextGenerator(answers: [
            'Coal produced 64% of power.',
            'Coal still powers much of the grid.',
        ]);
        $service = new SuggestionService($generator);

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Lengthen,
            language: 'en-GB',
            currentValue: 'Morocco is moving away from coal.',
        ));

        self::assertSame('Coal still powers much of the grid.', $result->text);
        self::assertCount(2, $generator->calls);
        self::assertStringContainsString('figures that are not in the text: 64%', $generator->calls[1]['user']);
    }

    public function testFiguresStillInventedAfterTheRetryFail(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(answer: 'Coal produced 64% of power.'));

        $this->expectException(InvalidModelOutputException::class);
        $service->suggest(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            currentValue: 'Morocco is moving away from coal.',
        ));
    }

    public function testFreePromptsAreNotCheckedForFigures(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(answer: 'Created 64% of content.'));

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Custom,
            language: 'en-GB',
            prompt: 'Write a stat.',
        ));

        self::assertSame('Created 64% of content.', $result->text);
    }

    public function testFiguresFromTheFullContentAreGroundedEvenIfNotInThePromptContext(): void
    {
        $generator = new FakeTextGenerator(answer: 'Coal supplied 64% of power.');
        $service = new SuggestionService($generator);

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Lengthen,
            language: 'en-GB',
            currentValue: 'Morocco is moving away from coal.',
            contentText: 'Long body text ... coal supplied 64% of power.',
        ));

        self::assertSame('Coal supplied 64% of power.', $result->text);
        self::assertCount(1, $generator->calls);
    }

    public function testReasoningBlocksNeverReachTheField(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(answer: "<think>Check the figures.</think>\n<p>Clear text.</p>"));

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            currentValue: '<p>Clear text.</p>',
        ));

        self::assertSame('<p>Clear text.</p>', $result->text);
    }

    public function testReasoningBlocksAreNotStreamed(): void
    {
        $service = new SuggestionService(new FakeTextGenerator(chunks: ['<think>draft', ' notes</think>', 'Final ', 'answer']));

        $text = implode('', iterator_to_array($service->stream(new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Summarize,
            language: 'en-GB',
            currentValue: 'Some text.',
        )), false));

        self::assertSame('Final answer', $text);
    }
}
