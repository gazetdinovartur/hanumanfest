<?php

namespace App\Tests\Functional\Admin;

use App\Entity\FaqItem;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class ContentReorderTest extends WebTestCase
{
    public function testFaqReorderUpdatesSortOrder(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $first = (new FaqItem())->setQuestion('A')->setAnswer('1')->setSortOrder(1)->setPublished(true);
        $second = (new FaqItem())->setQuestion('B')->setAnswer('2')->setSortOrder(2)->setPublished(true);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        $client->request('GET', '/admin/faq-item');
        self::assertResponseIsSuccessful();

        $csrf = $client->getCrawler()->filter('meta[name="hf-reorder-csrf"]')->attr('content');
        self::assertNotEmpty($csrf);

        $client->request(
            'POST',
            '/admin/cms/reorder/faq',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['ids' => [$second->getId(), $first->getId()], '_token' => $csrf], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $em->clear();
        self::assertSame(2, $em->find(FaqItem::class, $first->getId())?->getSortOrder());
        self::assertSame(1, $em->find(FaqItem::class, $second->getId())?->getSortOrder());
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
