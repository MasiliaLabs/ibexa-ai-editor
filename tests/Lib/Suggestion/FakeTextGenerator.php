<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Llm\TextGenerator;

/**
 * In-memory generator that returns canned output and records the prompts it received.
 */
final class FakeTextGenerator implements TextGenerator
{
    /** @var list<array{system: string, user: string}> */
    public array $calls = [];

    /**
     * @param list<string> $chunks
     * @param list<string> $answers consumed in order when given; otherwise $answer is always returned
     */
    public function __construct(
        private readonly string $answer = '',
        private readonly array $chunks = [],
        private array $answers = [],
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $this->calls[] = ['system' => $systemPrompt, 'user' => $userPrompt];

        return $this->answers !== [] ? array_shift($this->answers) : $this->answer;
    }

    public function stream(string $systemPrompt, string $userPrompt): iterable
    {
        $this->calls[] = ['system' => $systemPrompt, 'user' => $userPrompt];

        yield from $this->chunks;
    }
}
