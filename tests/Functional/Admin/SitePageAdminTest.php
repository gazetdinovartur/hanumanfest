<?php

namespace App\Tests\Functional\Admin;

use App\Entity\SitePage;
use App\Enum\SitePageTemplate;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testVideoFieldsAreOnKitchenTemplateOnly(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $legal = (new SitePage())
            ->setTitle('Политика')
            ->setSlug('politika-test')
            ->setContentHtml('<p>Текст</p>')
            ->setTemplate(SitePageTemplate::Default)
            ->setPublished(true);
        $kitchen = (new SitePage())
            ->setTitle('Питание')
            ->setSlug('pitanie-test')
            ->setContentHtml('<p>Еда</p>')
            ->setTemplate(SitePageTemplate::Kitchen)
            ->setKitchenVideo1('/uploads/pages/kitchen/IMG_8652.mp4')
            ->setPublished(true);
        $em->persist($legal);
        $em->persist($kitchen);
        $em->flush();

        $client->request('GET', sprintf('/admin/site-page/%d/edit', $legal->getId()));
        self::assertResponseIsSuccessful();
        $legalHtml = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-hf-page-template', $legalHtml);
        self::assertStringContainsString('hf-kitchen-videos', $legalHtml);
        self::assertStringNotContainsString('hf-kitchen-videos is-visible', $legalHtml);
        self::assertStringContainsString('admin-site-page.js', $legalHtml);
        self::assertStringContainsString('value="default"', $legalHtml);
        self::assertMatchesRegularExpression('/value="default"[^>]*selected|selected[^>]*value="default"/', $legalHtml);
        self::assertStringContainsString('Публикация', $legalHtml);

        $client->request('GET', sprintf('/admin/site-page/%d/edit', $kitchen->getId()));
        self::assertResponseIsSuccessful();
        $kitchenHtml = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('value="kitchen"', $kitchenHtml);
        self::assertMatchesRegularExpression('/value="kitchen"[^>]*selected|selected[^>]*value="kitchen"/', $kitchenHtml);
        self::assertStringContainsString('kitchenVideo1', $kitchenHtml);
        self::assertStringContainsString('Видео 1', $kitchenHtml);
        self::assertStringContainsString('hf-kitchen-videos is-visible', $kitchenHtml);
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
