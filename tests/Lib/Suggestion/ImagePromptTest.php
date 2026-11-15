<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Lib\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\ImagePrompt;
use Masilia\IbexaAiEditor\Suggestion\SiblingField;
use PHPUnit\Framework\TestCase;

final class ImagePromptTest extends TestCase
{
    public function testWithoutAnythingTypedTheSubjectIsTheItemItself(): void
    {
        $brief = ImagePrompt::build('', 'Event', 'Green Hydrogen Webinar Series: Episode 5', [
            new SiblingField('Short description', 'A webinar on the green hydrogen value chain.'),
        ]);

        self::assertStringContainsString('Subject: An image that illustrates “Green Hydrogen Webinar Series: Episode 5”.', $brief);
        self::assertStringContainsString('It illustrates a event titled', $brief);
        self::assertStringContainsString('- A webinar on the green hydrogen value chain.', $brief);
        self::assertStringContainsString('not a screenshot', mb_strtolower($brief));
        self::assertStringNotContainsString('Content type:', $brief);
    }

    public function testWhatTheEditorTypedLeadsTheBrief(): void
    {
        $brief = ImagePrompt::build('Solar panels in a green valley at sunrise', 'Event', 'Webinar', []);

        self::assertStringContainsString('Subject: Solar panels in a green valley at sunrise', $brief);
    }

    public function testSummariesComeFirstAndTechnicalFieldsAreLeftOut(): void
    {
        $brief = ImagePrompt::build('', 'Event', 'Webinar', [
            new SiblingField('Featured Image', 'banner.jpg and some long file description here'),
            new SiblingField('SEO meta', 'title: x description: a long seo description of the page'),
            new SiblingField('Location', 'Rabat, Morocco, in the main conference room'),
            new SiblingField('Short description', 'The summary of the webinar'),
        ]);

        self::assertStringNotContainsString('banner.jpg', $brief);
        self::assertStringNotContainsString('seo description', $brief);
        self::assertLessThan(strpos($brief, 'Rabat'), strpos($brief, 'The summary of the webinar'));
    }

    public function testMarkupIsStrippedAndTheBriefStaysShort(): void
    {
        $long = '<p>' . str_repeat('A long paragraph about hydrogen. ', 80) . '</p>';
        $brief = ImagePrompt::build('', 'Article', 'T', [
            new SiblingField('Description', $long),
            new SiblingField('Intro', $long . 'x'),
            new SiblingField('Summary', $long . 'y'),
        ]);

        self::assertStringNotContainsString('<p>', $brief);
        self::assertLessThanOrEqual(ImagePrompt::MAX_CHARS, mb_strlen($brief));
    }
}
