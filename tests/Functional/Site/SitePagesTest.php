<?php

namespace App\Tests\Functional\Site;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitePagesTest extends WebTestCase
{
    public function testHomePageIsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Хануман Фест');
    }

    public function testProgramPageIsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/program');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Программа фестиваля');
    }

    public function testRegistrationPageIsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/registration');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="registration"]');
    }

    public function testReturnPageIsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/return');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="return"]');
    }

    public function testPayPageIsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pay/test-token');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="payment"]');
    }
}
