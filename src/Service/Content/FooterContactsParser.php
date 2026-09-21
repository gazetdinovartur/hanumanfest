<?php

namespace App\Service\Content;

use App\Entity\SiteSettings;

final class FooterContactsParser
{
    /**
     * @return list<array{href: string, label: string}>
     */
    public function contactLinks(?SiteSettings $settings): array
    {
        if (!$settings instanceof SiteSettings) {
            return [];
        }

        $out = [];
        foreach ([$settings->getPhone(), $settings->getPhone2()] as $item) {
            $item = trim((string) $item);
            if ($item === '' || 1 !== preg_match('/\+?\d[\d\(\)\-\s]+$/u', $item)) {
                continue;
            }
            $tel = preg_replace('/\D+/', '', $item) ?? '';
            $out[] = ['href' => 'tel:'.$tel, 'label' => $item];
        }

        $email = trim((string) $settings->getEmail());
        if ($email !== '' && false !== filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[] = ['href' => 'mailto:'.$email, 'label' => $email];
        }

        return $out;
    }

    /**
     * @return list<array{url: string, name: string}>
     */
    public function socialLinks(?SiteSettings $settings): array
    {
        if (!$settings instanceof SiteSettings) {
            return [];
        }

        $out = [];
        foreach ([
            ['url' => $settings->getVkUrl(), 'name' => 'vk'],
            ['url' => $settings->getFacebookUrl(), 'name' => 'fb'],
            ['url' => $settings->getInstagramUrl(), 'name' => 'inst'],
            ['url' => $settings->getTelegramUrl(), 'name' => 'tg'],
        ] as $social) {
            $url = trim((string) $social['url']);
            if ($url === '') {
                continue;
            }
            $out[] = ['url' => $url, 'name' => $social['name']];
        }

        return $out;
    }

    /**
     * Телефоны, почта, Telegram и ВК — для письма и страницы после оплаты.
     *
     * @return list<array{href: string, label: string}>
     */
    public function feedbackContacts(?SiteSettings $settings): array
    {
        $items = $this->contactLinks($settings);
        foreach ($this->socialLinks($settings) as $social) {
            $label = match ($social['name']) {
                'tg' => 'Telegram',
                'vk' => 'ВКонтакте',
                default => null,
            };
            if ($label === null) {
                continue;
            }
            $items[] = ['href' => $social['url'], 'label' => $label];
        }

        return $items;
    }
}
