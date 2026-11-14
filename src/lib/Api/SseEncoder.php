<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Api;

use Masilia\Contracts\IbexaAi\Api\ApiError;
/**
 * Encodes the suggestion stream as named, versioned Server-Sent Events:
 *
 *   suggestion.delta      {"v":1,"text":"..."}                 visible text, in order
 *   suggestion.completed  {"v":1,"format":"html"}              the stream ended well
 *   suggestion.error      {"v":1,"code":"...","message":"..."}  the stream failed
 *
 * Clients ignore events they do not know and frames of another version, so events can be added later.
 * Keep-alive comments carry no event: they keep the connection busy while a reasoning model thinks.
 */
final class SseEncoder
{
    public const VERSION = 1;

    public const DELTA = 'suggestion.delta';
    public const COMPLETED = 'suggestion.completed';
    public const ERROR = 'suggestion.error';

    public function delta(string $text): string
    {
        return $this->event(self::DELTA, ['text' => $text]);
    }

    public function completed(string $format): string
    {
        return $this->event(self::COMPLETED, ['format' => $format]);
    }

    public function error(ApiError $error): string
    {
        return $this->event(self::ERROR, ['code' => $error->code, 'message' => $error->message]);
    }

    /**
     * An SSE comment: no event, ignored by clients, but it keeps the connection from going idle.
     */
    public function keepAlive(): string
    {
        return ": keep-alive\n\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    private function event(string $name, array $data): string
    {
        $payload = json_encode(['v' => self::VERSION] + $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return "event: {$name}\ndata: {$payload}\n\n";
    }
}
