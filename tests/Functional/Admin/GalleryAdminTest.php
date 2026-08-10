<?php

namespace App\Tests\Functional\Admin;

use App\Entity\GalleryItem;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class GalleryAdminTest extends WebTestCase
{
    public function testGalleryPageLoadsForAdmin(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/gallery');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hf-gallery__about', 'Как это было');
        self::assertSelectorExists('.hf-gallery');
        self::assertSelectorExists('.hf-gallery__about');
        self::assertSelectorExists('[data-filter="published"]');
    }

    public function testGalleryReorderUpdatesSortOrder(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $first = (new GalleryItem())->setImagePath('/uploads/gallery/a.jpg')->setSortOrder(1)->setPublished(true);
        $second = (new GalleryItem())->setImagePath('/uploads/gallery/b.jpg')->setSortOrder(2)->setPublished(true);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        $client->request('GET', '/admin/gallery');
        self::assertResponseIsSuccessful();

        $csrf = $client->getCrawler()->filter('.hf-gallery')->attr('data-csrf');
        self::assertNotEmpty($csrf);

        $client->request(
            'POST',
            '/admin/gallery/reorder',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$second->getId(), $first->getId()], '_token' => $csrf], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $em->clear();
        $reloadedFirst = $em->find(GalleryItem::class, $first->getId());
        $reloadedSecond = $em->find(GalleryItem::class, $second->getId());
        self::assertSame(2, $reloadedFirst?->getSortOrder());
        self::assertSame(1, $reloadedSecond?->getSortOrder());
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
