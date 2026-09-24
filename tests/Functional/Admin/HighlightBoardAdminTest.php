<?php

namespace App\Tests\Functional\Admin;

use App\Entity\HomeHighlight;
use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class HighlightBoardAdminTest extends WebTestCase
{
    public function testBoardShowsSiteLikeColumns(): void
    {
        $client = $this->adminClient();
        $this->seedTiles($client);

        $client->request('GET', '/admin/highlights');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hf-hl__lead', 'главной');
        self::assertSelectorExists('[data-column="left"]');
        self::assertSelectorExists('[data-column="right"]');
        self::assertSelectorTextContains('[data-column="left"]', 'Левая плитка');
        self::assertSelectorTextContains('[data-column="right"]', 'Правая плитка');
        self::assertSelectorExists('[data-column="left"] .hf-hl-tile--wide');
        self::assertSelectorExists('[data-column="right"] .hf-hl-tile--wide');
        self::assertSelectorExists('a[href*="column=left"]');
    }

    public function testCrudIndexRedirectsToBoard(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/home-highlight');
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-hf-highlights]');
    }

    public function testReorderSavesPerColumnAndCanMoveBetweenColumns(): void
    {
        $client = $this->adminClient();
        [$leftA, $leftB, $rightA] = $this->seedTiles($client);

        $client->request('GET', '/admin/highlights');
        self::assertResponseIsSuccessful();
        $csrf = $client->getCrawler()->filter('[data-hf-highlights]')->attr('data-csrf');
        self::assertNotEmpty($csrf);

        $client->request(
            'POST',
            '/admin/highlights/reorder',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'left' => [$leftB->getId()],
                'right' => [$rightA->getId(), $leftA->getId()],
                '_token' => $csrf,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        $em->clear();

        $moved = $em->find(HomeHighlight::class, $leftA->getId());
        $keptLeft = $em->find(HomeHighlight::class, $leftB->getId());
        $keptRight = $em->find(HomeHighlight::class, $rightA->getId());

        self::assertSame(HomeHighlightColumn::Right, $moved?->getColumnSide());
        self::assertSame(2, $moved?->getSortOrder());
        self::assertSame(HomeHighlightColumn::Left, $keptLeft?->getColumnSide());
        self::assertSame(1, $keptLeft?->getSortOrder());
        self::assertSame(1, $keptRight?->getSortOrder());
    }

    /** @return array{0: HomeHighlight, 1: HomeHighlight, 2: HomeHighlight} */
    private function seedTiles(KernelBrowser $client): array
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $leftA = (new HomeHighlight())
            ->setText('Левая плитка')
            ->setColumnSide(HomeHighlightColumn::Left)
            ->setStyle(HomeHighlightStyle::Big)
            ->setSortOrder(1)
            ->setPublished(true);
        $leftB = (new HomeHighlight())
            ->setText('Левая вторая')
            ->setColumnSide(HomeHighlightColumn::Left)
            ->setStyle(HomeHighlightStyle::Wide)
            ->setSortOrder(2)
            ->setPublished(true);
        $rightA = (new HomeHighlight())
            ->setText('Правая плитка')
            ->setColumnSide(HomeHighlightColumn::Right)
            ->setStyle(HomeHighlightStyle::Wide)
            ->setSortOrder(1)
            ->setPublished(true);
        $em->persist($leftA);
        $em->persist($leftB);
        $em->persist($rightA);
        $em->flush();

        return [$leftA, $leftB, $rightA];
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
