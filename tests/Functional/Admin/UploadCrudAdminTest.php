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
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Контент', $html);
        self::assertStringNotContainsString('>Шапка<', $html);
        self::assertStringNotContainsString('Фон hero', $html);
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
        self::assertSelectorExists('input[name$="[siteName]"]');
        self::assertSelectorExists('input[name$="[phone]"]');
        self::assertSelectorExists('input[name$="[email]"]');
        self::assertSelectorNotExists('input[name$="[tagline]"]');
        self::assertSelectorNotExists('textarea[name$="[contactsHtml]"]');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Слоган', $html);
        self::assertStringNotContainsString('Логотип в шапке', $html);
        self::assertStringNotContainsString('Email уведомлений', $html);
        self::assertLessThan(strpos($html, 'Блоки на главной'), strpos($html, 'Бренд'));
        self::assertLessThan(strpos($html, 'Контакты'), strpos($html, 'Блоки на главной'));
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

    public function testHomeHeroImageCanBeDeletedAndStaysDeleted(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $relative = 'hero/test-del-'.bin2hex(random_bytes(3)).'.png';
        $absolute = self::writeTinyPng($client, $relative);

        $hero = (new HomeHero())
            ->setHeadline('Тест')
            ->setTitleMain('Верх')
            ->setTitleSecondary('Низ')
            ->setAboutHtml('<p>О фестивале</p>')
            ->setImagePath('/uploads/'.$relative);
        $em->persist($hero);
        $em->flush();
        $heroId = $hero->getId();
        self::assertNotNull($heroId);

        $crawler = $client->request('GET', sprintf('/admin/home-hero/%d/edit', $heroId));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[imagePath][delete]"]');

        $form = $crawler->filter('form.ea-edit-form')->form();
        $form['HomeHero[imagePath][delete]']->tick();
        $client->submit($form);
        $this->followRedirects($client);

        $em->clear();
        $saved = $em->getRepository(HomeHero::class)->find($heroId);
        self::assertNotNull($saved);
        self::assertNull($saved->getImagePath());
        self::assertFileDoesNotExist($absolute);
    }

    public function testHomeHeroImageCanBeReplacedIntoHeroDirectory(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $relative = 'wp/2025/10/test-old-'.bin2hex(random_bytes(3)).'.png';
        $oldAbsolute = self::writeTinyPng($client, $relative);

        $hero = (new HomeHero())
            ->setHeadline('Тест')
            ->setImagePath('/uploads/'.$relative);
        $em->persist($hero);
        $em->flush();
        $heroId = $hero->getId();
        self::assertNotNull($heroId);

        $crawler = $client->request('GET', sprintf('/admin/home-hero/%d/edit', $heroId));
        self::assertResponseIsSuccessful();

        $upload = $this->tempUploadedPng();
        $form = $crawler->filter('form.ea-edit-form')->form();
        $form['HomeHero[imagePath][file]']->upload($upload);
        $client->submit($form);
        $this->followRedirects($client);

        $em->clear();
        $saved = $em->getRepository(HomeHero::class)->find($heroId);
        self::assertNotNull($saved);
        $newPath = (string) $saved->getImagePath();
        self::assertStringStartsWith('/uploads/hero/', $newPath);
        self::assertFileExists($this->projectDir($client).'/public'.$newPath);
        self::assertFileExists($oldAbsolute);

        @unlink($this->projectDir($client).'/public'.$newPath);
        @unlink($oldAbsolute);
    }

    public function testSiteSettingsLogoCanBeDeletedAndStaysDeleted(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $relative = 'site/test-logo-'.bin2hex(random_bytes(3)).'.png';
        $absolute = self::writeTinyPng($client, $relative);

        $settings = (new SiteSettings())
            ->setSiteName('Hanuman Fest')
            ->setLogoPath('/uploads/'.$relative);
        $em->persist($settings);
        $em->flush();
        $settingsId = $settings->getId();
        self::assertNotNull($settingsId);

        $crawler = $client->request('GET', sprintf('/admin/site-settings/%d/edit', $settingsId));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[logoPath][delete]"]');

        $form = $crawler->filter('form.ea-edit-form')->form();
        $form['SiteSettings[logoPath][delete]']->tick();
        $client->submit($form);
        $this->followRedirects($client);

        $em->clear();
        $saved = $em->getRepository(SiteSettings::class)->find($settingsId);
        self::assertNotNull($saved);
        self::assertNull($saved->getLogoPath());
        self::assertFileDoesNotExist($absolute);
    }

    private function followRedirects(KernelBrowser $client): void
    {
        for ($i = 0; $i < 4 && $client->getResponse()->isRedirect(); ++$i) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
    }

    private function projectDir(KernelBrowser $client): string
    {
        return $client->getContainer()->getParameter('kernel.project_dir');
    }

    private static function writeTinyPng(KernelBrowser $client, string $relativeWithinUploads): string
    {
        $absolute = $client->getContainer()->getParameter('kernel.project_dir').'/public/uploads/'.$relativeWithinUploads;
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolute, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ));

        return $absolute;
    }

    private function tempUploadedPng(): string
    {
        $path = sys_get_temp_dir().'/hf-up-'.bin2hex(random_bytes(3)).'.png';
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ));

        return $path;
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
