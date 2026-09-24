<?php

namespace App\Service\Content;

/**
 * Removes WordPress markup noise and normalizes CMS HTML / plain text.
 */
final class WpContentCleaner
{
    /**
     * HTML5 content tags. Anything else is stripped (inner text kept).
     * Media/embeds are removed earlier: img, video, iframe, figure, picture, source, audio, object, embed, svg.
     */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'address', 'article', 'aside',
        'b', 'bdi', 'bdo', 'blockquote', 'br',
        'caption', 'center', 'cite', 'code', 'col', 'colgroup',
        'data', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt',
        'em',
        'figcaption', 'font', 'footer',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr',
        'i', 'ins',
        'kbd',
        'li',
        'main', 'mark',
        'nav',
        'ol',
        'p', 'pre',
        'q',
        's', 'samp', 'section', 'small', 'span', 'strike', 'strong', 'sub', 'summary', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr',
        'u', 'ul',
        'var',
        'wbr',
    ];

    public function cleanHtml(?string $html): ?string
    {
        if (null === $html || '' === $html) {
            return $html;
        }

        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/u', '', $html) ?? $html;
        $html = preg_replace('/\r\n|\r/u', "\n", $html) ?? $html;

        $html = $this->stripMediaAndWpChrome($html);
        $html = strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');
        $html = $this->sanitizeAnchors($html);
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

    private function stripMediaAndWpChrome(string $html): string
    {
        $wrapped = '<div id="hf-clean-root">'.$html.'</div>';
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->stripMediaRegex($html);
        }

        $root = $dom->getElementById('hf-clean-root');
        if (!$root instanceof \DOMElement) {
            return $this->stripMediaRegex($html);
        }

        $removeTags = ['img', 'video', 'iframe', 'figure', 'picture', 'source', 'audio', 'object', 'embed', 'svg'];
        foreach ($removeTags as $tag) {
            /** @var list<\DOMElement> $nodes */
            $nodes = [];
            foreach ($root->getElementsByTagName($tag) as $node) {
                if ($node instanceof \DOMElement) {
                    $nodes[] = $node;
                }
            }
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@class]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $classes = preg_split('/\s+/u', (string) $node->getAttribute('class')) ?: [];
            $kept = array_values(array_filter(
                $classes,
                static fn (string $c): bool => $c !== ''
                    && !str_starts_with($c, 'wp-')
                    && !str_starts_with($c, 'align')
                    && !str_starts_with($c, 'size-')
                    && !str_starts_with($c, 'is-style-'),
            ));
            if ($kept === []) {
                $node->removeAttribute('class');
            } else {
                $node->setAttribute('class', implode(' ', $kept));
            }
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    private function stripMediaRegex(string $html): string
    {
        $html = preg_replace('#<(figure|picture|video|audio|iframe|object|embed|svg)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(img|source)\b[^>]*/?>#is', '', $html) ?? $html;

        return $html;
    }

    private function sanitizeAnchors(string $html): string
    {
        if (!str_contains($html, '<a')) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="hf-a-root">'.$html.'</div>';
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $html;
        }

        $root = $dom->getElementById('hf-a-root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        foreach ($root->getElementsByTagName('a') as $a) {
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            $allowed = $href !== '' && (
                str_starts_with($href, 'http://')
                || str_starts_with($href, 'https://')
                || str_starts_with($href, 'mailto:')
                || str_starts_with($href, 'tel:')
                || str_starts_with($href, '/')
                || str_starts_with($href, '#')
            );
            if (!$allowed) {
                $a->removeAttribute('href');
            }
            foreach (iterator_to_array($a->attributes ?? []) as $attr) {
                if (!$attr instanceof \DOMAttr) {
                    continue;
                }
                $name = strtolower($attr->name);
                if (!in_array($name, ['href', 'title', 'target', 'rel'], true)) {
                    $a->removeAttribute($attr->name);
                }
            }
            if ($a->hasAttribute('target') && $a->getAttribute('target') === '_blank' && !$a->hasAttribute('rel')) {
                $a->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }
}
