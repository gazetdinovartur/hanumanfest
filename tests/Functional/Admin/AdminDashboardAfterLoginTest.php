<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class AdminDashboardAfterLoginTest extends WebTestCase
{
    public function testDashboardOpensAfterLogin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);

        $crawler = $client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('input[name="_csrf_token"]')->count());

        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'admin',
            '_password' => 'TempAdmin!2026',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin', (string) $client->getResponse()->headers->get('Location'));
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.admin-dashboard');
        self::assertSelectorTextContains('.admin-dashboard', 'регистраций');
        self::assertSelectorTextContains('.admin-dashboard', 'оплачено');
        self::assertSelectorTextContains('.admin-dashboard', 'возвратов');
        self::assertSelectorTextContains('.admin-dashboard', 'Регистрации');
        self::assertStringNotContainsString('Частые действия', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('.content-top .hf-admin-season');
        self::assertSelectorNotExists('.sidebar-wrapper .hf-admin-season');
        self::assertSelectorNotExists('.dropdown-settings');
        self::assertSelectorTextContains('.hf-admin-season', 'Сезоны');
    }
}
