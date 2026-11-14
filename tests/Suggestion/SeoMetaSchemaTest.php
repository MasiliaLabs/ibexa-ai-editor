<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Suggestion;

use Masilia\IbexaAiEditor\Suggestion\SeoMetaField;
use Masilia\IbexaAiEditor\Suggestion\SeoMetaSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeoMetaSchema::class)]
final class SeoMetaSchemaTest extends TestCase
{
    public function testFallbackCoversConventionalKeys(): void
    {
        $keys = array_keys(SeoMetaSchema::fallback()->fields);

        self::assertContains('title', $keys);
        self::assertContains('og:image', $keys);
        self::assertCount(12, $keys);
    }

    public function testRestrictToKeepsOnlyTheFormKeysCaseInsensitively(): void
    {
        $schema = SeoMetaSchema::fallback()->restrictTo(['TITLE', 'description']);

        self::assertSame(['title', 'description'], array_keys($schema->fields));
    }

    public function testEmptyOrNonMatchingKeysKeepTheFullSchema(): void
    {
        $full = SeoMetaSchema::fallback();

        self::assertSame($full, $full->restrictTo([]));
        self::assertSame($full, $full->restrictTo(['unknown-key']));
    }

    public function testCustomSchemaKeepsItsOwnLimits(): void
    {
        $schema = new SeoMetaSchema(['title' => new SeoMetaField('title', 'Page title', 70)]);

        self::assertSame(70, $schema->fields['title']->maxLength);
    }
}
