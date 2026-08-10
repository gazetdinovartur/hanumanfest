<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Person;
use App\Enum\PersonKind;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class PeopleAdminSectionsTest extends WebTestCase
{
    public function testGuestSectionListsGuestsOnly(): void
    {
        $client = $this->adminClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $this->seedPeople($em);

        $client->request('GET', '/admin/guest-person');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Специальные гости');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Гость А', $html);
        self::assertStringNotContainsString('Музыкант Б', $html);
        self::assertStringNotContainsString('Мастер В', $html);
    }

    public function testMusicianSectionListsMusiciansOnly(): void
    {
        $client = $this->adminClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $this->seedPeople($em);

        $client->request('GET', '/admin/musician-person');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Музыканты');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Музыкант Б', $html);
        self::assertStringNotContainsString('Гость А', $html);
        self::assertStringNotContainsString('Мастер В', $html);
    }

    public function testMasterSectionListsMastersOnly(): void
    {
        $client = $this->adminClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $this->seedPeople($em);

        $client->request('GET', '/admin/master-person');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Мастера и практики');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Мастер В', $html);
        self::assertStringNotContainsString('Гость А', $html);
        self::assertStringNotContainsString('Музыкант Б', $html);
    }

    private function seedPeople($em): void
    {
        $people = [
            ['Гость А', PersonKind::Guest],
            ['Музыкант Б', PersonKind::Musician],
            ['Мастер В', PersonKind::Master],
        ];

        foreach ($people as $index => [$name, $kind]) {
            $person = (new Person())
                ->setName($name)
                ->setKind($kind)
                ->setSortOrder($index + 1)
                ->setPublished(true);
            $em->persist($person);
        }
        $em->flush();
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $schemaTool = new SchemaTool($client->getContainer()->get('doctrine')->getManager());
        $metadata = $client->getContainer()->get('doctrine')->getManager()->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($client->getContainer()->get('doctrine')->getManager());
        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return $client;
    }
}
