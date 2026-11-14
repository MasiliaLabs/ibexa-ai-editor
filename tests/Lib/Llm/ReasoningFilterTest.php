<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Llm;

use Masilia\IbexaAiEditor\Llm\ReasoningFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReasoningFilter::class)]
final class ReasoningFilterTest extends TestCase
{
    public function testCompleteAnswersLoseTheirReasoningBlock(): void
    {
        self::assertSame(
            '<p>OK</p>',
            trim(ReasoningFilter::strip("<think>We need OK.</think>\n<p>OK</p>")),
        );
    }

    public function testAnUnclosedReasoningBlockYieldsNothingAfterIt(): void
    {
        self::assertSame('Answer ', ReasoningFilter::strip('Answer <think>still thinking'));
    }

    public function testStreamedTagsSplitAcrossChunksAreHidden(): void
    {
        $filter = new ReasoningFilter();
        $chunks = ['Hello <thi', 'nk>secret reasoning', ' </th', 'ink> world'];

        $visible = '';
        foreach ($chunks as $chunk) {
            $visible .= $filter->feed($chunk);
        }
        $visible .= $filter->flush();

        self::assertSame('Hello  world', $visible);
    }

    public function testATagLikeFragmentThatIsNotATagIsEmittedAtTheEnd(): void
    {
        $filter = new ReasoningFilter();

        $visible = $filter->feed('x <') . $filter->feed('b>bold</b>') . $filter->flush();

        self::assertSame('x <b>bold</b>', $visible);
    }

    public function testNoReasoningMeansNoChange(): void
    {
        $filter = new ReasoningFilter();

        self::assertSame('plain text', $filter->feed('plain ') . $filter->feed('text') . $filter->flush());
    }
}
