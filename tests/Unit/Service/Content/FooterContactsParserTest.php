<?php

namespace App\Tests\Unit\Service\Content;

use App\Entity\SiteSettings;
use App\Service\Content\FooterContactsParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FooterContactsParserTest extends TestCase
{
    public function testParsesPhonesEmailAndSocialsLikeWpFooter(): void
    {
        $settings = (new SiteSettings())
            ->setPhone('+7 (343) 385-83-70')
            ->setPhone2('+7 922 211 61 18')
            ->setEmail('hanuman-yoga@bk.ru')
            ->setVkUrl('https://vk.com/hanumanyoga')
            ->setFacebookUrl('https://www.facebook.com/hanumanyoga.ru/')
            ->setInstagramUrl('https://www.instagram.com/hanuman_yoga.ru/')
            ->setTelegramUrl('https://t.me/Hanuman_ekb');

        $parser = new FooterContactsParser();

        self::assertSame([
            ['href' => 'tel:73433858370', 'label' => '+7 (343) 385-83-70'],
            ['href' => 'tel:79222116118', 'label' => '+7 922 211 61 18'],
            ['href' => 'mailto:hanuman-yoga@bk.ru', 'label' => 'hanuman-yoga@bk.ru'],
        ], $parser->contactLinks($settings));

        self::assertSame([
            ['url' => 'https://vk.com/hanumanyoga', 'name' => 'vk'],
            ['url' => 'https://www.facebook.com/hanumanyoga.ru/', 'name' => 'fb'],
            ['url' => 'https://www.instagram.com/hanuman_yoga.ru/', 'name' => 'inst'],
            ['url' => 'https://t.me/Hanuman_ekb', 'name' => 'tg'],
        ], $parser->socialLinks($settings));

        self::assertSame([
            ['href' => 'tel:73433858370', 'label' => '+7 (343) 385-83-70'],
            ['href' => 'tel:79222116118', 'label' => '+7 922 211 61 18'],
            ['href' => 'mailto:hanuman-yoga@bk.ru', 'label' => 'hanuman-yoga@bk.ru'],
            ['href' => 'https://vk.com/hanumanyoga', 'label' => 'ВКонтакте'],
            ['href' => 'https://t.me/Hanuman_ekb', 'label' => 'Telegram'],
        ], $parser->feedbackContacts($settings));
    }

    public function testEmptySettingsYieldNoLinks(): void
    {
        $parser = new FooterContactsParser();

        self::assertSame([], $parser->contactLinks(null));
        self::assertSame([], $parser->socialLinks(new SiteSettings()));
        self::assertSame([], $parser->feedbackContacts(new SiteSettings()));
    }
}
