<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Llm;

use Generator;
use Masilia\IbexaAiEditor\Llm\TextGenerator;
use Masilia\Contracts\IbexaAi\Observability\NullRequestLogger;
use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\ProviderResponse;
use Throwable;

/**
 * Adapts a Neuron provider to the {@see TextGenerator} port and records every call
 * (latency, token usage, finish reason or error class). Prompts and answers are never logged.
 *
 * One instance wraps one provider built for one request, so setting the system prompt on it
 * does not leak into other requests.
 */
final readonly class NeuronTextGenerator implements TextGenerator
{
    public function __construct(
        private AIProviderInterface $provider,
        private RequestLogger $logger = new NullRequestLogger(),
        private string $providerIdentifier = '',
        private string $modelIdentifier = '',
        private ?string $siteaccess = null,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $start = hrtime(true);

        try {
            /** @var ProviderResponse $response */
            $response = $this->provider
                ->systemPrompt($systemPrompt)
                ->chat(new UserMessage($userPrompt));
        } catch (Throwable $e) {
            $this->logFailure($start, $e);

            throw $e;
        }

        $this->logSuccess($start, $response);

        return $response->message()->getContent() ?? '';
    }

    /**
     * @return Generator<int, string>
     */
    public function stream(string $systemPrompt, string $userPrompt): Generator
    {
        $start = hrtime(true);

        try {
            $chunks = $this->provider
                ->systemPrompt($systemPrompt)
                ->stream(new UserMessage($userPrompt));

            foreach ($chunks as $chunk) {
                if ($chunk instanceof TextChunk && $chunk->content !== '') {
                    yield $chunk->content;
                }
            }

            $response = $chunks->getReturn();
        } catch (Throwable $e) {
            $this->logFailure($start, $e);

            throw $e;
        }

        if ($response instanceof ProviderResponse) {
            $this->logSuccess($start, $response);
        } else {
            $this->logSuccess($start, null);
        }
    }

    private function logSuccess(int $start, ?ProviderResponse $response): void
    {
        $usage = $response?->message()->getUsage();

        $this->logger->log(new RequestLog(
            providerIdentifier: $this->providerIdentifier,
            modelIdentifier: $this->modelIdentifier,
            siteaccess: $this->siteaccess,
            success: true,
            latencyMs: $this->elapsedMs($start),
            tokensIn: $usage?->inputTokens,
            tokensOut: $usage?->outputTokens,
            finishReason: $response?->message()->stopReason(),
        ));
    }

    private function logFailure(int $start, Throwable $e): void
    {
        $this->logger->log(new RequestLog(
            providerIdentifier: $this->providerIdentifier,
            modelIdentifier: $this->modelIdentifier,
            siteaccess: $this->siteaccess,
            success: false,
            latencyMs: $this->elapsedMs($start),
            errorCode: (new \ReflectionClass($e))->getShortName(),
        ));
    }

    private function elapsedMs(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
