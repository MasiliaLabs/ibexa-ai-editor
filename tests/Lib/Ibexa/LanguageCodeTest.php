<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Ibexa;

use Masilia\IbexaAiEditor\Ibexa\LanguageCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageCode::class)]
final class LanguageCodeTest extends TestCase
{
    #[DataProvider('sameLanguagePairs')]
    public function testSameLanguageAcrossCodeShapes(string $a, string $b, bool $expected): void
    {
        self::assertSame($expected, LanguageCode::sameLanguage($a, $b));
    }

    public static function sameLanguagePairs(): iterable
    {
        yield 'short vs ibexa french' => ['fr', 'fre-FR', true];
        yield 'short vs ibexa english' => ['en', 'eng-GB', true];
        yield 'two regions of english' => ['eng-US', 'eng-GB', true];
        yield 'different languages' => ['fre-FR', 'eng-GB', false];
        yield 'empty never matches' => ['', 'eng-GB', false];
    }

    public function testResolvePrefersAnExactCodeThenThePrimaryLanguage(): void
    {
        $available = ['eng-GB', 'fre-FR'];

        self::assertSame('fre-FR', LanguageCode::resolve('fre-FR', $available));
        self::assertSame('fre-FR', LanguageCode::resolve('fr', $available));
        self::assertNull(LanguageCode::resolve('de', $available));
    }
}
