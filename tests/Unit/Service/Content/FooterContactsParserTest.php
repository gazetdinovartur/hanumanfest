<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\FooterContactsParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FooterContactsParserTest extends TestCase
{
    public function testParsesPhonesEmailAndSocialsLikeWpFooter(): void
    {
        $raw = "+7 (343) 385-83-70\n+7 922 211 61 18\nhanumanfest@gmail.com\nhttps://vk.com/hanumanyoga\nhttps://www.facebook.com/hanumanyoga.ru/\nhttps://www.instagram.com/hanuman_yoga.ru/\nhttps://t.me/Hanuman_ekb";

        $parser = new FooterContactsParser();

        self::assertSame([
            ['href' => 'tel:73433858370', 'label' => '+7 (343) 385-83-70'],
            ['href' => 'tel:79222116118', 'label' => '+7 922 211 61 18'],
            ['href' => 'mailto:hanumanfest@gmail.com', 'label' => 'hanumanfest@gmail.com'],
        ], $parser->contactLinks($raw));

        self::assertSame([
            ['url' => 'https://vk.com/hanumanyoga', 'name' => 'vk'],
            ['url' => 'https://www.facebook.com/hanumanyoga.ru/', 'name' => 'fb'],
            ['url' => 'https://www.instagram.com/hanuman_yoga.ru/', 'name' => 'inst'],
            ['url' => 'https://t.me/Hanuman_ekb', 'name' => 'tg'],
        ], $parser->socialLinks($raw));

        self::assertSame([
            ['href' => 'tel:73433858370', 'label' => '+7 (343) 385-83-70'],
            ['href' => 'tel:79222116118', 'label' => '+7 922 211 61 18'],
            ['href' => 'mailto:hanumanfest@gmail.com', 'label' => 'hanumanfest@gmail.com'],
            ['href' => 'https://vk.com/hanumanyoga', 'label' => 'ВКонтакте'],
            ['href' => 'https://t.me/Hanuman_ekb', 'label' => 'Telegram'],
        ], $parser->feedbackContacts($raw));
    }
}
