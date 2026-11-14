<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

use Masilia\Contracts\IbexaAi\Llm\ReasoningFilter;
use Generator;
use Masilia\IbexaAiEditor\Llm\TextGenerator;

/**
 * Entry point for field suggestions: builds prompts, calls the model,
 * and cleans the answer.
 */
final readonly class SuggestionService
{
    public function __construct(
        private TextGenerator $generator,
        private PromptBuilder $prompts = new PromptBuilder(),
        private ResponseSanitizer $sanitizer = new ResponseSanitizer(),
    ) {
    }

    /**
     * @throws InvalidModelOutputException when a JSON field gets a non-object answer,
     *                                     or a rewrite keeps adding figures absent from the source
     */
    public function suggest(SuggestionRequest $request): Suggestion
    {
        $format = $request->outputFormat();
        $system = $this->prompts->systemPrompt($request);
        $user = $this->prompts->userPrompt($request);

        $text = $this->sanitizer->clean($this->generator->generate($system, $user), $format);

        if ($request->requiresGrounding()) {
            $invented = GroundingCheck::ungroundedNumbers($text, $request->groundingText());

            if ($invented !== []) {
                $retry = $user . sprintf(
                    "\n\nYour previous answer added figures that are not in the text: %s. "
                    . "Rewrite it using only figures that appear in the text above.",
                    implode(', ', $invented),
                );
                $text = $this->sanitizer->clean($this->generator->generate($system, $retry), $format);

                if (GroundingCheck::ungroundedNumbers($text, $request->groundingText()) !== []) {
                    throw new InvalidModelOutputException('The model added figures that are not in the text.');
                }
            }
        }

        return new Suggestion($text, $format);
    }

    /**
     * Visible text as the model produces it. Reasoning blocks are removed; other
     * wrappers are not, because the prompt forbids them and the full text is sanitised by the editor.
     *
     * @return Generator<int, string>
     */
    public function stream(SuggestionRequest $request): Generator
    {
        foreach ($this->streamWithActivity($request) as $text) {
            if ($text !== null) {
                yield $text;
            }
        }
    }

    /**
     * Like {@see stream()}, but also yields null each time the model produced something that is not visible
     * (its reasoning), so a caller can keep a connection busy while the model thinks.
     *
     * @return Generator<int, string|null>
     */
    public function streamWithActivity(SuggestionRequest $request): Generator
    {
        $reasoning = new ReasoningFilter();

        foreach ($this->generator->stream(
            $this->prompts->systemPrompt($request),
            $this->prompts->userPrompt($request),
        ) as $chunk) {
            $visible = $reasoning->feed($chunk);
            yield $visible !== '' ? $visible : null;
        }

        $rest = $reasoning->flush();
        if ($rest !== '') {
            yield $rest;
        }
    }
}
