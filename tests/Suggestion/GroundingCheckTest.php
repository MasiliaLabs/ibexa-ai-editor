<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\GroundingCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroundingCheck::class)]
final class GroundingCheckTest extends TestCase
{
    public function testFiguresPresentInTheSourceAreAccepted(): void
    {
        self::assertSame([], GroundingCheck::ungroundedNumbers('Phase out by 2040.', 'Morocco Coal Phaseout 2040'));
    }

    public function testInventedFiguresAreReported(): void
    {
        $invented = GroundingCheck::ungroundedNumbers(
            'In 2023, coal produced 64% of power; NDC 3.0 is due.',
            'Morocco is moving away from coal.',
        );

        self::assertSame(['2023', '64%', '3.0'], $invented);
    }

    public function testSingleDigitsAreIgnored(): void
    {
        self::assertSame([], GroundingCheck::ungroundedNumbers('1. Start 2. Finish', 'no numbers here'));
    }
}
