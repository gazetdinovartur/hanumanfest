<?php

namespace App\Tests\Functional\Admin;

use App\Entity\FestivalSeason;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class FestivalSeasonAdminTest extends WebTestCase
{
    public function testSeasonCrudIsReachableOutsideSidebar(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/festival-season');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content-header', 'Сезоны');
        self::assertSelectorExists('.content-top .hf-admin-season');
        self::assertSelectorNotExists('.sidebar-wrapper .hf-admin-season');
        self::assertSelectorNotExists('.dropdown-settings');
        self::assertSelectorTextContains('.hf-admin-season', '2026');
    }

    public function testNewSeasonPrefillsNextYearAndCanBecomeCurrent(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/admin/festival-season/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.ea-new-form');
        self::assertSame('2027', $crawler->filter('input[name="FestivalSeason[year]"]')->attr('value'));
        self::assertSame('Хануман Фест 2027', $crawler->filter('input[name="FestivalSeason[name]"]')->attr('value'));

        $form = $crawler->filter('form.ea-new-form')->form([
            'FestivalSeason[year]' => '2028',
            'FestivalSeason[name]' => 'Хануман Фест 2028',
            'FestivalSeason[isCurrent]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $em->clear();

        $created = $em->getRepository(FestivalSeason::class)->findOneBy(['year' => 2028]);
        $previous = $em->getRepository(FestivalSeason::class)->findOneBy(['year' => 2026]);

        self::assertNotNull($created);
        self::assertSame('Хануман Фест 2028', $created->getName());
        self::assertTrue($created->isCurrent());
        self::assertNotNull($previous);
        self::assertFalse($previous->isCurrent());

        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hf-admin-season__tab.is-selected', '2028');
        self::assertSelectorTextContains('.admin-dashboard__title', 'Хануман Фест 2028');

        $tab = $crawler->filter(sprintf('.hf-admin-season__tab[value="%d"]', $previous->getId()));
        self::assertGreaterThan(0, $tab->count());
        $client->submit($tab->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.hf-admin-season__tab.is-selected', '2026');
        self::assertSelectorTextContains('.admin-dashboard__title', 'Хануман Фест 2026');
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
