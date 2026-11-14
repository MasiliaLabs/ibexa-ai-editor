<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Llm;

/**
 * Port between the suggestion logic and a concrete LLM. The Neuron adapter
 * implements it in the bundle layer; tests use an in-memory fake.
 */
interface TextGenerator
{
    /**
     * Single-shot completion. Returns the full text.
     */
    public function generate(string $systemPrompt, string $userPrompt): string;

    /**
     * Incremental completion. Yields text chunks in order.
     *
     * @return iterable<string>
     */
    public function stream(string $systemPrompt, string $userPrompt): iterable;
}
