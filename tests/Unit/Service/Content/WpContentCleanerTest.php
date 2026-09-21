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
        $input = '<!-- wp:paragraph --><p>Текст</p><!-- /wp:paragraph -->';
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

    public function testCleanHtmlRemovesFiguresImagesAndVideos(): void
    {
        $input = '<p>Привет</p><figure class="wp-block-image size-large"><img src="/x.jpg" class="wp-image-1" alt=""></figure><video src="/a.mp4"></video><p>Конец</p>';
        $out = $this->cleaner->cleanHtml($input);
        self::assertNotNull($out);
        self::assertStringContainsString('<p>Привет</p>', $out);
        self::assertStringContainsString('<p>Конец</p>', $out);
        self::assertStringNotContainsString('<img', $out);
        self::assertStringNotContainsString('<figure', $out);
        self::assertStringNotContainsString('<video', $out);
    }

    public function testCleanHtmlKeepsListsAndLinks(): void
    {
        $input = '<ul><li>Один</li></ul><p><a href="https://example.com" onclick="alert(1)">Ссылка</a></p>';
        $out = $this->cleaner->cleanHtml($input);
        self::assertNotNull($out);
        self::assertStringContainsString('<ul>', $out);
        self::assertStringContainsString('<li>Один</li>', $out);
        self::assertStringContainsString('href="https://example.com"', $out);
        self::assertStringNotContainsString('onclick', $out);
    }
}
