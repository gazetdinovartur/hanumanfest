<?php

namespace App\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class AdminDashboardAfterLoginTest extends WebTestCase
{
    public function testDashboardOpensAfterLogin(): void
    {
        $client = static::createClient();
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
    }
}
