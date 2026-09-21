<?php

namespace App\Tests\Functional\Admin;

use App\Entity\ParticipationOption;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('functional')]
final class PricingMatrixTest extends WebTestCase
{
    public function testPricingMatrixPageLoadsForAdmin(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/pricing');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-hf-pricing-matrix]');
        self::assertSelectorExists('[data-hf-add-period]');
        self::assertSelectorExists('[data-hf-add-option]');
        self::assertSelectorTextContains('h1', 'Периоды и цены');
        self::assertSelectorExists('input[name^="options["][name$="[name]"]');
        self::assertSelectorExists('script[src*="admin-pricing-matrix.js"]');
        self::assertSelectorExists('.hf-price__settings input[name="transferPrice"]');
        self::assertSelectorTextContains('.hf-price__settings', 'Трансфер, ₽/чел');
        self::assertSelectorNotExists('.hf-price__settings-title');
        self::assertSelectorNotExists('input[name*="[transferPrice]"]');
        self::assertSelectorExists('.page-actions button.hf-price__save[form="hf-pricing-form"]');
        self::assertSelectorNotExists('.hf-price__bar');
        $html = (string) $client->getResponse()->getContent();
        self::assertLessThan(
            strpos($html, 'name="transferPrice"'),
            strpos($html, 'data-hf-body'),
            'Трансфер должен быть под матрицей периодов',
        );
    }

    public function testPricingMatrixShowsSeededOptionName(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/pricing');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[value="в своем жилье (домик или палатка), без питания"]');
    }

    public function testPricingMatrixSaveAddsNewParticipationOption(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $client->request('GET', '/admin/pricing');
        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();
        $token = $crawler->filter('#hf-pricing-form input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        $period = $em->getRepository(PricingPeriod::class)->findOneBy([]);
        self::assertNotNull($period);
        $option = $em->getRepository(ParticipationOption::class)->findOneBy([]);
        self::assertNotNull($option);

        $periodKey = (string) $period->getId();
        $optionKey = (string) $option->getId();
        $newOptionKey = 'new_testoption';

        $client->request('POST', '/admin/pricing', [
            '_token' => $token,
            'transferPrice' => '600',
            'periods' => [
                $periodKey => [
                    'name' => $period->getName(),
                    'startAt' => $period->getStartAt()->format('Y-m-d\TH:i'),
                    'endAt' => $period->getEndAt()->format('Y-m-d\TH:i'),
                    'isActive' => '1',
                ],
            ],
            'options' => [
                $optionKey => ['name' => $option->getName()],
                $newOptionKey => ['name' => 'В нашей палатке, с питанием'],
            ],
            'prices' => [
                $periodKey => [
                    $optionKey => '3600',
                    $newOptionKey => '7400',
                ],
            ],
        ]);

        self::assertResponseRedirects('/admin/pricing');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $em->clear();
        $saved = $em->getRepository(ParticipationOption::class)->findOneBy(['name' => 'В нашей палатке, с питанием']);
        self::assertNotNull($saved);
        self::assertSame(7400, $this->findPrice($em, $period->getId(), $saved->getId()));
    }

    public function testPricingMatrixSavesTransferPrice(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $client->request('GET', '/admin/pricing');
        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();
        $token = $crawler->filter('#hf-pricing-form input[name="_token"]')->attr('value');
        $period = $em->getRepository(PricingPeriod::class)->findOneBy([]);
        $option = $em->getRepository(ParticipationOption::class)->findOneBy([]);
        self::assertNotEmpty($token);
        self::assertNotNull($period);
        self::assertNotNull($option);

        $periodKey = (string) $period->getId();
        $optionKey = (string) $option->getId();

        $client->request('POST', '/admin/pricing', [
            '_token' => $token,
            'transferPrice' => '750',
            'periods' => [
                $periodKey => [
                    'name' => $period->getName(),
                    'startAt' => $period->getStartAt()->format('Y-m-d\TH:i'),
                    'endAt' => $period->getEndAt()->format('Y-m-d\TH:i'),
                    'isActive' => '1',
                ],
            ],
            'options' => [
                $optionKey => ['name' => $option->getName()],
            ],
            'prices' => [
                $periodKey => [
                    $optionKey => '3600',
                ],
            ],
        ]);

        self::assertResponseRedirects('/admin/pricing');
        $em->clear();
        $saved = $em->getRepository(Product::class)->find($period->getProduct()?->getId());
        self::assertNotNull($saved);
        self::assertSame(750, $saved->getTransferPrice());
    }

    public function testPricingMatrixColumnsAreSortedByStartThenEnd(): void
    {
        $client = $this->adminClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();

        $product = $em->getRepository(Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        self::assertNotNull($product);
        $season = HanumanFestFixtures::currentSeason($em);

        $late = new PricingPeriod();
        $late->setProduct($product);
        $late->setSeason($season);
        $late->setName('Позже');
        $late->setStartAt(new \DateTimeImmutable('2027-06-01 00:00:00'));
        $late->setEndAt(new \DateTimeImmutable('2027-08-01 23:59:00'));
        $late->setIsActive(true);
        $em->persist($late);

        $sameStartShorter = new PricingPeriod();
        $sameStartShorter->setProduct($product);
        $sameStartShorter->setSeason($season);
        $sameStartShorter->setName('Короткий');
        $sameStartShorter->setStartAt(new \DateTimeImmutable('2026-01-01 00:00:00'));
        $sameStartShorter->setEndAt(new \DateTimeImmutable('2026-02-01 23:59:00'));
        $sameStartShorter->setIsActive(true);
        $em->persist($sameStartShorter);
        $em->flush();

        $crawler = $client->request('GET', '/admin/pricing');
        self::assertResponseIsSuccessful();

        $starts = $crawler->filter('[data-hf-period-col] input[name$="[startAt]"]')->each(
            static fn ($node) => $node->attr('value')
        );
        $ends = $crawler->filter('[data-hf-period-col] input[name$="[endAt]"]')->each(
            static fn ($node) => $node->attr('value')
        );

        self::assertSame(
            ['2026-01-01T00:00', '2026-01-01T00:00', '2027-06-01T00:00'],
            $starts
        );
        self::assertSame(
            ['2026-02-01T23:59', '2026-12-31T23:59', '2027-08-01T23:59'],
            $ends
        );
    }

    public function testPricingMatrixPageHasOptionColumnWidthVariable(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/admin/pricing');

        self::assertResponseIsSuccessful();
        $style = $crawler->filter('[data-hf-pricing-matrix]')->attr('style');
        self::assertNotNull($style);
        self::assertStringContainsString('--hf-option-name-ch:', $style);
    }

    private function findPrice(EntityManagerInterface $em, ?int $periodId, ?int $optionId): ?int
    {
        if (null === $periodId || null === $optionId) {
            return null;
        }

        $period = $em->getRepository(PricingPeriod::class)->find($periodId);
        self::assertNotNull($period);

        foreach ($period->getParticipationPrices() as $price) {
            if ($price->getParticipationOption()?->getId() === $optionId) {
                return $price->getPrice();
            }
        }

        return null;
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
