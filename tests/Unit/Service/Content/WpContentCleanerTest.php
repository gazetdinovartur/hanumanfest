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

    public function testCleanHtmlKeepsAboutFestivalHeadingMarkup(): void
    {
        $input = '<h2 class="text-brand-main mb-4" style="text-align: center;">В 100 км от Екатеринбурга и в 100 км от Челябинска</h2>'
            ."\n"
            .'<h4 class="text-brand-accent mb-4" style="text-align: center;">В живописном месте в окружении семи озёр.</h4>';
        $out = $this->cleaner->cleanHtml($input);
        self::assertNotNull($out);
        self::assertStringContainsString('<h2', $out);
        self::assertStringContainsString('class="text-brand-main mb-4"', $out);
        self::assertStringContainsString('text-align: center', $out);
        self::assertStringContainsString('В 100 км от Екатеринбурга и в 100 км от Челябинска', $out);
        self::assertStringContainsString('<h4', $out);
        self::assertStringContainsString('text-brand-accent', $out);
        self::assertStringNotContainsString('<script', $out);
    }

    public function testCleanHtmlKeepsKitchenPageStructure(): void
    {
        $input = '<h2>Основное питание</h2><div style="height:40px" aria-hidden="true"></div><hr><p class="h4 text-brand-main mt-4">Накормим</p>';
        $out = $this->cleaner->cleanHtml($input);
        self::assertNotNull($out);
        self::assertStringContainsString('<h2>Основное питание</h2>', $out);
        self::assertStringContainsString('<div', $out);
        self::assertStringContainsString('height:40px', $out);
        self::assertStringContainsString('<hr', $out);
        self::assertStringContainsString('class="h4 text-brand-main mt-4"', $out);
    }

    public function testCleanHtmlStillStripsWpHeadingClassNoise(): void
    {
        $out = $this->cleaner->cleanHtml('<h2 class="wp-block-heading text-brand-main">Заголовок</h2>');
        self::assertNotNull($out);
        self::assertStringContainsString('<h2', $out);
        self::assertStringContainsString('text-brand-main', $out);
        self::assertStringNotContainsString('wp-block-heading', $out);
    }

    public function testCleanHtmlKeepsCommonContentTags(): void
    {
        $input = '<section><article><h2>Заголовок</h2><blockquote><p>Цитата</p></blockquote>'
            .'<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>'
            .'<dl><dt>Термин</dt><dd>Определение</dd></dl>'
            .'<pre><code>code()</code></pre>'
            .'<p>H<sub>2</sub>O <sup>2</sup> <mark>важно</mark></p>'
            .'<details><summary>Спойлер</summary><p>Текст</p></details>'
            .'</article></section>';
        $out = $this->cleaner->cleanHtml($input);
        self::assertNotNull($out);
        foreach (['<section', '<article', '<blockquote', '<table', '<thead', '<th', '<td', '<dl', '<dt', '<dd', '<pre', '<code', '<sub', '<sup', '<mark', '<details', '<summary'] as $tag) {
            self::assertStringContainsString($tag, $out);
        }
        self::assertStringContainsString('Цитата', $out);
        self::assertStringContainsString('Определение', $out);
    }

    public function testCleanHtmlStillStripsScriptFormsAndIframes(): void
    {
        $out = $this->cleaner->cleanHtml(
            '<p>Ок</p><script>alert(1)</script><form action="/"><input name="x"></form><iframe src="https://evil.example"></iframe>',
        );
        self::assertNotNull($out);
        self::assertStringContainsString('<p>Ок</p>', $out);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('<form', $out);
        self::assertStringNotContainsString('<input', $out);
        self::assertStringNotContainsString('<iframe', $out);
    }
}
