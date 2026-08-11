<?php

namespace App\Service\Content;

/**
 * Parses footer contacts the same way as the WP theme footer.php.
 */
final class FooterContactsParser
{
    /** @var array<string, string> */
    private const SOCIAL_DOMAINS = [
        'vk.com' => 'vk',
        'facebook.com' => 'fb',
        'instagram.com' => 'inst',
        't.me' => 'tg',
        'wa.me' => 'wa',
    ];

    /**
     * @return list<array{href: string, label: string}>
     */
    public function contactLinks(?string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $item) {
            if (false !== filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $out[] = ['href' => 'mailto:'.$item, 'label' => $item];
                continue;
            }

            if (1 === preg_match('/\+?\d[\d\(\)\-\s]+$/u', $item)) {
                $tel = preg_replace('/\D+/', '', $item) ?? '';
                $out[] = ['href' => 'tel:'.$tel, 'label' => $item];
            }
        }

        return $out;
    }

    /**
     * @return list<array{url: string, name: string}>
     */
    public function socialLinks(?string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $item) {
            foreach (self::SOCIAL_DOMAINS as $domain => $name) {
                if (str_contains($item, $domain)) {
                    $out[] = ['url' => $item, 'name' => $name];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function lines(?string $raw): array
    {
        if (null === $raw || '' === trim($raw)) {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $line): bool => '' !== $line));
    }
}
