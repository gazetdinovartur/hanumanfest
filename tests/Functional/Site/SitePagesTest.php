<?php

namespace App\Tests\Functional\Site;

use App\Entity\HomeHighlight;
use App\Entity\SitePage;
use App\Entity\SiteSettings;
use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use App\Enum\SitePageTemplate;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SitePagesTest extends WebTestCase
{
    public function testHomePageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $this->seedFooterSettings($client);
        $this->seedHomeHighlights($client);
        $this->seedCmsPages($client);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Хануман Фест', $html);
        self::assertStringContainsString('id="cost"', $html);
        self::assertStringContainsString('pricingAccordion', $html, $html);
        self::assertStringContainsString('При оплате до 10 марта', $html);
        self::assertMatchesRegularExpression('/3[\s\x{00a0}]?600\s*₽/u', $html);
        self::assertStringContainsString('в своем жилье (домик или палатка), без питания', $html);

        self::assertStringContainsString('Политика возвратов', $html);
        self::assertStringContainsString('Публичная оферта', $html);
        self::assertStringContainsString('Политика конфиденциальности', $html);
        self::assertStringContainsString('tel:73433858370', $html);
        self::assertStringContainsString('mailto:hanuman-yoga@bk.ru', $html);
        self::assertStringContainsString('https://vk.com/hanumanyoga', $html);
        self::assertStringContainsString('https://www.facebook.com/hanumanyoga.ru/', $html);
        self::assertStringContainsString('https://www.instagram.com/hanuman_yoga.ru/', $html);
        self::assertStringContainsString('https://t.me/Hanuman_ekb', $html);
        self::assertStringContainsString('ОГРНИП 304662518300032', $html);
        self::assertStringContainsString('ИНН 662504951300', $html);
        self::assertStringContainsString('ИП Сараев Антон Валерьевич', $html);
        self::assertStringContainsString('footer-fb', $html);
        self::assertStringContainsString('footer-inst', $html);
        self::assertStringContainsString('icons/vk.svg', $html);
        self::assertStringContainsString('icons/fb.svg', $html);
        self::assertStringContainsString('icons/inst.svg', $html);
        self::assertStringContainsString('icons/tg.svg', $html);
        self::assertStringContainsString('id="about"', $html);
        self::assertStringContainsString('href="/#about"', $html);
        self::assertStringContainsString('class="is-home"', $html);
        self::assertStringContainsString('Море йоги, музыки и творчества', $html);
        self::assertStringContainsString('code.jivo.ru/widget/qNhdVN9jC1', $html);
    }

    public function testCmsPagesAreOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $this->seedCmsPages($client);
        $router = $client->getContainer()->get('router');

        $client->request('GET', $router->generate('site_page', ['slug' => 'политика-возвратов']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Политика возвратов');
        self::assertSelectorNotExists('section.promo-grid');
        self::assertStringNotContainsString('IMG_8652.mp4', (string) $client->getResponse()->getContent());

        $client->request('GET', $router->generate('site_page', ['slug' => 'публичная-оферта']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Публичная оферта');

        $client->request('GET', $router->generate('site_page', ['slug' => 'политика-конфиденциальности']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Политика конфиденциальности');

        $client->request('GET', $router->generate('site_page', ['slug' => 'питание-на-хануман-фест']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Питание на Хануман Фест');
        self::assertSelectorExists('section.promo-grid');
        self::assertStringContainsString('Фудкорт', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('IMG_8652.mp4', (string) $client->getResponse()->getContent());
    }

    public function testUnknownCmsPageReturns404(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $router = $client->getContainer()->get('router');

        $client->request('GET', $router->generate('site_page', ['slug' => 'несуществующая-страница']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testReviewsAndGalleryArchivePagesAreOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $this->seedFooterSettings($client);
        $router = $client->getContainer()->get('router');

        $client->request('GET', $router->generate('site_reviews'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Отзывы участников');

        $client->request('GET', $router->generate('site_gallery'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Как это было');
    }

    public function testProgramPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/program');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Программа фестиваля');
        self::assertSelectorNotExists('body.is-home');
    }

    public function testRegistrationPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/registration');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="registration"]');
        self::assertSelectorExists('[data-uae-conditional="tent-roommate"]');
        self::assertSelectorExists('[name="tentRoommate"]');
        self::assertSelectorExists('[data-uae-conditional="house-booking"]');
        self::assertSelectorExists('a[href="https://www.helloferma.ru/"]');
        self::assertStringContainsString('С кем будете проживать в палатке', $client->getResponse()->getContent());
        self::assertStringContainsString('Ссылка на бронирование домика', $client->getResponse()->getContent());
    }

    public function testReturnPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $this->seedFooterSettings($client);
        $client->request('GET', '/return');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-widget="return"]');
        self::assertSelectorExists('[data-uae-copy-btn]');
        self::assertSelectorExists('[data-uae-return-pay]');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Вы зарегистрировались и внесли предоплату за участие в Хануман Фест!', $html);
        self::assertStringContainsString('Если есть вопросы, напишите нам', $html);
        self::assertSelectorExists('.hf-return-contacts');
        self::assertSelectorExists('.hf-return-contacts__link');
        self::assertStringContainsString('Ссылка на оплату остатка', $html);
    }

    public function testPayPageIsOk(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/pay/test-token');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ссылка недействительна', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('[data-uae-pay-btn]');
    }

    public function testPayPageShowsHumanRemainderForValidLink(): void
    {
        $client = static::createClient();
        $em = $this->bootSchema($client);

        $user = new \App\Entity\User();
        $user->setName('Анна');
        $user->setEmail('anna-pay@test.example');
        $user->setPhone('+79160000021');
        $em->persist($user);

        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new \App\Entity\Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(\App\Enum\ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload(['participationOptionName' => 'в своем жилье (домик или палатка), без питания']);
        $em->persist($application);
        $em->flush();

        /** @var \App\Service\PaymentLinkService $links */
        $links = $client->getContainer()->get(\App\Service\PaymentLinkService::class);
        $link = $links->createForApplication($application);
        $link->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $em->flush();

        $client->request('GET', '/pay/'.$link->getToken());

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Оплата остатка', $html);
        self::assertStringContainsString('Анна', $html);
        self::assertStringContainsString('Анкету заполнять заново не нужно', $html);
        self::assertSelectorExists('[data-uae-pay-btn]');
        self::assertStringNotContainsString((string) $application->getUuid(), $html);
    }

    public function testPayPageForUnpaidHalfPaymentShowsAmountDueNow(): void
    {
        $client = static::createClient();
        $em = $this->bootSchema($client);
        $testOption = HanumanFestFixtures::enableRegistrationTestMode($em);
        $optionId = $testOption->getId();
        self::assertNotNull($optionId);

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Предоплата',
                'email' => 'half-pay-page@test.example',
                'phone' => '+79001112255',
                'participationOptionId' => $optionId,
                'adultsCount' => 1,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $uuid = json_decode($client->getResponse()->getContent(), true)['uuid'] ?? '';

        $application = $em->getRepository(\App\Entity\Application::class)->findOneBy(['uuid' => $uuid]);
        self::assertNotNull($application);

        /** @var \App\Service\PaymentLinkService $links */
        $links = $client->getContainer()->get(\App\Service\PaymentLinkService::class);
        $link = $links->ensureForPartialApplication($application);
        self::assertNotNull($link);

        $client->request('GET', '/pay/'.$link->getToken());
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('К оплате сейчас', $html);
        self::assertStringContainsString('Оплатить 1 ₽', $html);
        self::assertStringNotContainsString('Оплатить 2 ₽', $html);
        self::assertStringContainsString('Всего по заявке', $html);
    }

    public function testRegistrationPageOffersExistingPaymentHintMarkup(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $client->request('GET', '/registration');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-uae-existing-payment]');
        self::assertSelectorExists('[data-uae-existing-cancel]');
        self::assertSelectorExists('[data-uae-factor-hint="0.5"]');
        self::assertSelectorExists('[data-uae-factor-hint="1"]');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Сейчас спишется половина', $html);
        self::assertStringContainsString('Сейчас спишется вся сумма', $html);
        $submitPos = strpos($html, 'data-uae-submit');
        $noticePos = strpos($html, 'data-uae-existing-payment');
        $errorPos = strpos($html, 'data-uae-error');
        self::assertNotFalse($submitPos);
        self::assertNotFalse($noticePos);
        self::assertNotFalse($errorPos);
        self::assertGreaterThan($submitPos, $noticePos);
        self::assertGreaterThan($submitPos, $errorPos);
    }

    private function bootSchema(KernelBrowser $client): \Doctrine\ORM\EntityManagerInterface
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        HanumanFestFixtures::seed($em);

        return $em;
    }

    private function seedFooterSettings(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $settings = (new SiteSettings())
            ->setSiteName('Хануман Фест')
            ->setCompanyInfo("ИП Сараев Антон Валерьевич\n\nОГРНИП 304662518300032\nИНН 662504951300")
            ->setPhone('+7 (343) 385-83-70')
            ->setPhone2('+7 922 211 61 18')
            ->setEmail('hanuman-yoga@bk.ru')
            ->setVkUrl('https://vk.com/hanumanyoga')
            ->setTelegramUrl('https://t.me/Hanuman_ekb')
            ->setFacebookUrl('https://www.facebook.com/hanumanyoga.ru/')
            ->setInstagramUrl('https://www.instagram.com/hanuman_yoga.ru/');
        $em->persist($settings);
        $em->flush();
    }

    private function seedCmsPages(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $pages = [
            ['политика-возвратов', 'Политика возвратов', '<p>Возврат</p>', SitePageTemplate::Default, true, 10],
            ['публичная-оферта', 'Публичная оферта', '<p>Оферта</p>', SitePageTemplate::Default, true, 20],
            ['политика-конфиденциальности', 'Политика конфиденциальности', '<p>Privacy</p>', SitePageTemplate::Default, true, 30],
            ['питание-на-хануман-фест', 'Питание на Хануман Фест', '<h2>Фудкорт</h2><p>Еда</p>', SitePageTemplate::Kitchen, false, 100],
        ];
        foreach ($pages as [$slug, $title, $html, $template, $footer, $sort]) {
            $page = (new SitePage())
                ->setSlug($slug)
                ->setTitle($title)
                ->setContentHtml($html)
                ->setTemplate($template)
                ->setShowInFooter($footer)
                ->setSortOrder($sort)
                ->setPublished(true);
            if ($template === SitePageTemplate::Kitchen) {
                $page->setKitchenVideo1('/uploads/wp/2026/03/IMG_8652.mp4')
                    ->setKitchenVideo2('/uploads/wp/2026/03/IMG_8223.mp4')
                    ->setKitchenVideo3('/uploads/wp/2026/03/IMG_8224.mp4')
                    ->setKitchenVideo4('/uploads/wp/2026/03/IMG_8222.mp4');
            }
            $em->persist($page);
        }
        $em->flush();
    }

    private function seedHomeHighlights(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        $tile = (new HomeHighlight())
            ->setText('Море йоги, музыки и творчества')
            ->setColumnSide(HomeHighlightColumn::Left)
            ->setStyle(HomeHighlightStyle::Big)
            ->setSortOrder(1)
            ->setPublished(true);
        $em->persist($tile);
        $em->flush();
    }
}
