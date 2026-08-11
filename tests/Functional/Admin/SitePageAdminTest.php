<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class SitePageAdminTest extends WebTestCase
{
    public function testSitePagesCrudIsReachableForAdmin(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/site-page');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Страницы', (string) $client->getResponse()->getContent());
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);
        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return $client;
    }
}
