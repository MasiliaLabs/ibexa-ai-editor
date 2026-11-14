<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Llm;

use Masilia\Bundle\IbexaAiEditor\Llm\NeuronTextGenerator;
use Masilia\IbexaAiEditor\Suggestion\SuggestionAction;
use Masilia\IbexaAiEditor\Suggestion\SuggestionRequest;
use Masilia\IbexaAiEditor\Suggestion\SuggestionService;
use Masilia\IbexaAiEditor\Suggestion\SupportedField;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NeuronTextGenerator::class)]
final class NeuronTextGeneratorTest extends TestCase
{
    public function testGenerateSendsSystemPromptAndReturnsAnswer(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Result text'));
        $generator = new NeuronTextGenerator($provider);

        $answer = $generator->generate('SYSTEM RULES', 'USER TEXT');

        self::assertSame('Result text', $answer);
        $provider->assertCallCount(1);
        $provider->assertSystemPrompt('SYSTEM RULES');
    }

    public function testStreamConcatenatesTextChunksInOrder(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Streamed answer with several chunks'));
        $generator = new NeuronTextGenerator($provider);

        $text = '';
        foreach ($generator->stream('SYSTEM', 'USER') as $chunk) {
            $text .= $chunk;
        }

        self::assertSame('Streamed answer with several chunks', $text);
    }

    public function testSuggestionServiceWorksEndToEndOverANeuronProvider(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage("```html\n<p>Better</p>\n```"));
        $service = new SuggestionService(new NeuronTextGenerator($provider));

        $result = $service->suggest(new SuggestionRequest(
            field: SupportedField::RichText,
            action: SuggestionAction::Improve,
            language: 'en-GB',
            currentValue: '<p>Plain</p>',
        ));

        self::assertSame('<p>Better</p>', $result->text);
        $provider->assertCallCount(1);
        $provider->assertSent(static fn ($record): bool => str_contains($record->systemPrompt?->getContent() ?? '', 'Do NOT use <h1>'));
    }

    public function testSuccessIsLoggedWithUsageAndWithoutTheAnswerText(): void
    {
        $message = new AssistantMessage('Secret answer text');
        $message->setUsage(new \NeuronAI\Chat\Messages\Usage(120, 45));
        $message->setStopReason('stop');
        $logger = new \Masilia\IbexaAiEditor\Tests\Llm\CollectingRequestLogger();
        $generator = new NeuronTextGenerator(new FakeAIProvider($message), $logger, 'minimax', 'MiniMax-M3', 'admin');

        $generator->generate('SYSTEM', 'USER');

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertTrue($entry->success);
        self::assertSame(120, $entry->tokensIn);
        self::assertSame(45, $entry->tokensOut);
        self::assertSame('stop', $entry->finishReason);
        self::assertSame('admin', $entry->siteaccess);
        self::assertStringNotContainsString('Secret answer', print_r($entry, true));
    }

    public function testFailureIsLoggedWithTheErrorClassAndRethrown(): void
    {
        $logger = new \Masilia\IbexaAiEditor\Tests\Llm\CollectingRequestLogger();
        $generator = new NeuronTextGenerator(new FakeAIProvider(), $logger, 'openai', 'gpt-x');

        try {
            $generator->generate('SYSTEM', 'USER');
            self::fail('an exhausted fake provider must throw');
        } catch (\Throwable) {
        }

        self::assertCount(1, $logger->entries);
        self::assertFalse($logger->entries[0]->success);
        self::assertNotNull($logger->entries[0]->errorCode);
    }
}
