<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ApplicationCreateApiTest extends WebTestCase
{
    public function testCreatesApplicationWithTentRoommateForOurTentOption(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy([]);
        self::assertNotNull($product);
        self::assertNotNull($period);

        $option = new ParticipationOption();
        $option->setProduct($product);
        $option->setCode('OUR_TENT_FOOD');
        $option->setName('в нашей палатке, с питанием');
        $em->persist($option);

        $price = new \App\Entity\ParticipationPrice();
        $price->setPricingPeriod($period);
        $price->setParticipationOption($option);
        $price->setPrice(7400);
        $em->persist($price);
        $em->flush();

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Тест Палатка',
                'email' => 'tent-roommate@example.com',
                'phone' => '+79001234567',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 1,
                'tentRoommate' => 'С сестрой Марией',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $application = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertSame('С сестрой Марией', $application->getPayload()['tentRoommate'] ?? null);
    }

    public function testDropsTentRoommateForOwnHouseOption(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Тест Домик',
                'email' => 'house-booking@example.com',
                'phone' => '+79007654321',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 1,
                'tentRoommate' => 'не должно сохраниться',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $application = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertArrayNotHasKey('tentRoommate', $application->getPayload());
    }

    public function testSameEmailCanRegisterInAnotherSeason(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);
        $body = json_encode([
            'name' => 'Возвращение',
            'email' => 'returning@example.com',
            'phone' => '+79001112233',
            'participationOptionId' => $option->getId(),
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 1,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(409);

        $em->clear();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);
        $product = $option->getProduct();
        self::assertNotNull($product);

        $season2026 = $em->getRepository(FestivalSeason::class)->findOneBy(['year' => 2026]);
        self::assertNotNull($season2026);
        $season2026->setIsCurrent(false);

        $season2027 = new FestivalSeason();
        $season2027->setYear(2027);
        $season2027->setName('Хануман Фест 2027');
        $season2027->setIsCurrent(true);
        $em->persist($season2027);

        $product = $option->getProduct();
        $period = new PricingPeriod();
        $period->setProduct($product);
        $period->setSeason($season2027);
        $period->setName('До 10 марта');
        $period->setStartAt(new \DateTimeImmutable('2026-01-01 00:00:00'));
        $period->setEndAt(new \DateTimeImmutable('2027-12-31 23:59:59'));
        $period->setIsActive(true);
        $em->persist($period);

        $price = new ParticipationPrice();
        $price->setPricingPeriod($period);
        $price->setParticipationOption($option);
        $price->setPrice(3600);
        $em->persist($price);
        $em->flush();

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(2, $em->getRepository(Application::class)->findAll());
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
