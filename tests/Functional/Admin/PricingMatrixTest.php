<?php

namespace App\Tests\Functional\Admin;

use App\Entity\ParticipationOption;
use App\Entity\PricingPeriod;
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
        $token = $crawler->filter('input[name="_token"]')->attr('value');
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
