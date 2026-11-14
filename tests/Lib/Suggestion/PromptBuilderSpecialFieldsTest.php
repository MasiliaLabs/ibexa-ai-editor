<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\MatrixContext;
use Masilia\IbexaAiEditor\Suggestion\PromptBuilder;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptBuilder::class)]
final class PromptBuilderSpecialFieldsTest extends TestCase
{
    public function testMatrixRulesListColumnIdentifiersAndRowCount(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Matrix,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            matrix: new MatrixContext(['text' => 'Button label', 'url' => 'Link'], 3),
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('key: "text" (human label: Button label)', $system);
        self::assertStringContainsString('key: "url" (human label: Link)', $system);
        self::assertStringContainsString('must contain 3 row(s)', $system);
    }

    public function testMatrixWithoutColumnsFallsBackToValueKey(): void
    {
        $request = new SuggestionRequest(SupportedField::Matrix, SuggestionAction::Improve, 'en-GB');

        self::assertStringContainsString('use a single "value" key', (new PromptBuilder())->systemPrompt($request));
    }

    public function testMatrixTranslationAsksForRowsJson(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Matrix,
            action: SuggestionAction::Translate,
            language: 'fre-FR',
            sourceLanguage: 'eng-GB',
            sourceValue: '{"rows":[]}',
        );

        $user = (new PromptBuilder())->userPrompt($request);

        self::assertStringStartsWith('Translate each cell of the following matrix from eng-GB to fre-FR.', $user);
        self::assertStringContainsString('{"rows":[]}', $user);
    }

    public function testWholeBlockSeoSchemaIsRestrictedToFormKeys(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::SeoMetas,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            metaKeys: ['title', 'description'],
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('"title": "Title (compelling, click-worthy SEO Meta Title, under 60 characters)"', $system);
        self::assertStringContainsString('"description": "Description', $system);
        self::assertStringNotContainsString('"keywords"', $system);
        self::assertStringContainsString('Output MUST be a valid JSON object', $system);
    }

    public function testSeoSubFieldAsksForPlainTextWithItsLimit(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::SeoMetas,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            subFieldKey: 'description',
        );

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('Keep it under 160 characters.', $system);
        self::assertStringContainsString('NO JSON', $system);
    }

    public function testRewritesForbidInventedFacts(): void
    {
        $request = new SuggestionRequest(SupportedField::Text, SuggestionAction::Improve, 'en-GB');

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('Do NOT add facts, figures, percentages, dates', $system);
    }

    public function testLengthenAllowsElaborationButNotNewFacts(): void
    {
        $request = new SuggestionRequest(SupportedField::RichText, SuggestionAction::Lengthen, 'en-GB');

        $system = (new PromptBuilder())->systemPrompt($request);

        self::assertStringContainsString('You may elaborate on what the text already says', $system);
        self::assertStringContainsString('Do NOT add facts, figures', $system);
    }

    public function testTranslationKeepsTheSameInformation(): void
    {
        $request = new SuggestionRequest(
            field: SupportedField::Text,
            action: SuggestionAction::Translate,
            language: 'fre-FR',
            sourceLanguage: 'eng-GB',
            sourceValue: 'Hello',
        );

        self::assertStringContainsString('do not add, remove or reorder information', (new PromptBuilder())->systemPrompt($request));
    }

    public function testFreePromptsAndImagesAreNotConstrainedByTheFaithfulnessRules(): void
    {
        $custom = new SuggestionRequest(SupportedField::Text, SuggestionAction::Custom, 'en-GB', prompt: 'Write a poem.');
        $image = new SuggestionRequest(SupportedField::Image, SuggestionAction::Improve, 'en-GB');

        self::assertStringNotContainsString('Faithfulness rules', (new PromptBuilder())->systemPrompt($custom));
        self::assertStringNotContainsString('Faithfulness rules', (new PromptBuilder())->systemPrompt($image));
    }
}
