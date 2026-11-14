<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\FieldFormat;
use Masilia\IbexaAiEditor\Suggestion\PromptBuilder;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptBuilder::class)]
final class PromptBuilderTest extends TestCase
{
    public function testQuickActionUsesItsInstruction(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Shorten,
            language: 'en-GB',
            currentValue: 'A long paragraph.',
        );

        $user = (new PromptBuilder())->userPrompt($request);

        self::assertStringStartsWith(
            'Make this text more concise while preserving all key information and meaning.',
            $user,
        );
        self::assertStringContainsString('"""A long paragraph."""', $user);
    }

    public function testCustomActionUsesTheFreePrompt(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::String,
            action: SuggestionAction::Custom,
            language: 'en-GB',
            prompt: 'Write a catchy title about dunes.',
        );

        self::assertSame('Write a catchy title about dunes.', (new PromptBuilder())->userPrompt($request));
    }

    public function testTranslationNamesBothLanguagesAndIncludesSource(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Translate,
            language: 'fr-FR',
            sourceLanguage: 'en-GB',
            sourceValue: '<p>Hello</p>',
        );

        $user = (new PromptBuilder())->userPrompt($request);

        self::assertStringContainsString('Translate the following en-GB content to fr-FR.', $user);
        self::assertStringEndsWith("\n\n<p>Hello</p>", $user);
    }

    public function testSystemPromptCarriesFormatRulesForRichText(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Improve,
            language: 'en-GB',
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString(FieldFormat::HTML_ALLOWED_TAGS, $system);
        self::assertStringContainsString('Do NOT use <h1>', $system);
        self::assertStringStartsWith('You are a professional content writing assistant for a CMS.', $system);
    }

    public function testNonEnglishLanguageIsStatedInTheSystemPrompt(): void
    {
        $fr = new SuggestionRequest(SupportedField::Text, SuggestionAction::Improve, 'fr-FR');
        $en = new SuggestionRequest(SupportedField::Text, SuggestionAction::Improve, 'en-GB');

        self::assertStringContainsString('Write in language code: fr-FR.', (new PromptBuilder())->systemPrompt($fr));
        self::assertStringNotContainsString('Write in language code', (new PromptBuilder())->systemPrompt($en));
    }

    public function testSiblingFieldsAreScrubbedAndTruncated(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            contentTitle: 'Say "hi"',
            siblingFields: [
                new SiblingField('Summary', "line one\nline \"two\""),
                new SiblingField('Empty', ''),
            ],
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('Content title: "Say \"hi\"".', $system);
        self::assertStringContainsString('- Summary: "line one line \"two\""', $system);
        self::assertStringNotContainsString('- Empty:', $system);
    }

    public function testImageFieldGetsAltTextRules(): void
    {
        $request = new SuggestionRequest(SupportedField::Image, SuggestionAction::Improve, 'en-GB');

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('Alt text generation rules:', $system);
        self::assertStringContainsString('Keep it under 125 characters.', $system);
        self::assertStringNotContainsString('Rules:', $system);
    }
}
