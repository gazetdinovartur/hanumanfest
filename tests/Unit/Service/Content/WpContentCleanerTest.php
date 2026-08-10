<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\WpContentCleaner;
use PHPUnit\Framework\TestCase;

final class WpContentCleanerTest extends TestCase
{
    private WpContentCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new WpContentCleaner();
    }

    public function testCleanHtmlRemovesWpParagraphComments(): void
    {
        $input = "<!-- wp:paragraph --><p>Текст</p><!-- /wp:paragraph -->";
        self::assertSame('<p>Текст</p>', $this->cleaner->cleanHtml($input));
    }

    public function testCleanHtmlReturnsNullForEmptyResult(): void
    {
        self::assertNull($this->cleaner->cleanHtml('<!-- wp:paragraph --> <!-- /wp:paragraph -->'));
    }

    public function testCleanPlainStripsTagsAndWpComments(): void
    {
        $input = '<!-- wp:paragraph --><p>Кратко</p><!-- /wp:paragraph -->';
        self::assertSame('Кратко', $this->cleaner->cleanPlain($input));
    }
}
