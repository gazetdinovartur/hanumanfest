<?php

namespace App\Tests\Functional\Site;

use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SitePagesTest extends WebTestCase
{
    public function testHomePageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Хануман Фест', $html);
        self::assertStringContainsString('id="cost"', $html);
        self::assertStringContainsString('pricingAccordion', $html, $html);
        self::assertStringContainsString('При оплате до 10 марта', $html);
        self::assertMatchesRegularExpression('/3[\s\x{00a0}]?600\s*₽/u', $html);
        self::assertStringContainsString('в своем жилье (домик или палатка), без питания', $html);
    }

    public function testProgramPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/program');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Программа фестиваля');
    }

    public function testRegistrationPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/registration');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="registration"]');
    }

    public function testReturnPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/return');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="return"]');
    }

    public function testPayPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/pay/test-token');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="payment"]');
    }

    private function bootSchema(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
    }
}
