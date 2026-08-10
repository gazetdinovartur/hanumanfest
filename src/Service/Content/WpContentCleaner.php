<?php

namespace App\Service\Content;

/**
 * Removes WordPress block comments and normalizes CMS text fields.
 */
final class WpContentCleaner
{
    public function cleanHtml(?string $html): ?string
    {
        if (null === $html || '' === $html) {
            return $html;
        }

        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/u', '', $html) ?? $html;
        $html = preg_replace('/\r\n|\r/u', "\n", $html) ?? $html;
        $html = preg_replace("/\n{3,}/u", "\n\n", $html) ?? $html;
        $html = trim($html);

        return '' === $html ? null : $html;
    }

    public function cleanPlain(?string $text): ?string
    {
        if (null === $text || '' === $text) {
            return $text;
        }

        $text = $this->cleanHtml($text);
        if (null === $text) {
            return null;
        }

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return '' === $text ? null : $text;
    }
}
