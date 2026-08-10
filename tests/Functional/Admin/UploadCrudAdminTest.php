<?php

namespace App\Tests\Functional\Admin;

use App\Entity\HomeHero;
use App\Entity\Person;
use App\Entity\SiteSettings;
use App\Enum\PersonKind;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class UploadCrudAdminTest extends WebTestCase
{
    public function testHomeHeroEditFormLoadsWithLegacyUploadPaths(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $hero = (new HomeHero())
            ->setHeadline('Тест')
            ->setImagePath('/uploads/wp/2025/10/hero.jpg')
            ->setPromoVideoLeft('/uploads/wp/2026/02/left.mp4')
            ->setPromoVideoRight('/uploads/wp/2026/02/right.mp4');
        $em->persist($hero);
        $em->flush();

        $client->request('GET', sprintf('/admin/home-hero/%d/edit', $hero->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.ea-edit-form');
    }

    public function testSiteSettingsEditFormLoadsWithLegacyLogoPath(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $settings = (new SiteSettings())
            ->setSiteName('Hanuman Fest')
            ->setLogoPath('/uploads/wp/2025/10/logo-hanuman.png')
            ->setFooterBackgroundPath('/uploads/wp/2025/10/footer.jpg');
        $em->persist($settings);
        $em->flush();

        $client->request('GET', sprintf('/admin/site-settings/%d/edit', $settings->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.ea-edit-form');
    }

    public function testPersonEditFormLoadsWithPhotoField(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $person = (new Person())
            ->setKind(PersonKind::Guest)
            ->setName('Тестовый гость')
            ->setPhotoPath('/uploads/wp/2026/02/guest.jpg')
            ->setPublished(true)
            ->setSortOrder(1);
        $em->persist($person);
        $em->flush();

        $client->request('GET', sprintf('/admin/guest-person/%d/edit', $person->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.ea-edit-form');
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->loginUser(new InMemoryUser('admin', 'TempAdmin!2026', ['ROLE_SUPER_ADMIN']), 'main');

        return $client;
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
