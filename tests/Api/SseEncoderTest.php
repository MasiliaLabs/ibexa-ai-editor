<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Api;

use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\IbexaAiEditor\Api\SseEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SseEncoder::class)]
#[CoversClass(ApiError::class)]
final class SseEncoderTest extends TestCase
{
    public function testDeltaIsANamedVersionedEvent(): void
    {
        self::assertSame(
            "event: suggestion.delta\ndata: {\"v\":1,\"text\":\"Hi\"}\n\n",
            (new SseEncoder())->delta('Hi'),
        );
    }

    public function testATextWithNewlinesStaysOnOneDataLineAndDecodesIdentically(): void
    {
        $frame = (new SseEncoder())->delta("Énergie\nsolaire");

        // event line, one data line, then the blank line that ends the frame: a raw newline in the text would add lines.
        $lines = explode("\n", $frame);
        self::assertCount(4, $lines);
        self::assertSame('event: suggestion.delta', $lines[0]);
        self::assertStringStartsWith('data: ', $lines[1]);
        self::assertSame("Énergie\nsolaire", json_decode(substr($lines[1], 6), true)['text']);
    }

    public function testCompletedCarriesTheFormat(): void
    {
        self::assertSame(
            "event: suggestion.completed\ndata: {\"v\":1,\"format\":\"html\"}\n\n",
            (new SseEncoder())->completed('html'),
        );
    }

    public function testErrorCarriesTheCodeAndTheMessage(): void
    {
        self::assertSame(
            "event: suggestion.error\ndata: {\"v\":1,\"code\":\"SERVICE_UNAVAILABLE\",\"message\":\"down\"}\n\n",
            (new SseEncoder())->error(ApiError::serviceUnavailable('down')),
        );
    }

    public function testKeepAliveIsAnEventlessComment(): void
    {
        self::assertSame(": keep-alive\n\n", (new SseEncoder())->keepAlive());
    }

    public function testAccessDeniedEnvelope(): void
    {
        self::assertSame(
            ['error' => ['code' => 'ACCESS_DENIED', 'message' => 'Access Denied']],
            ApiError::accessDenied()->toArray(),
        );
    }
}
