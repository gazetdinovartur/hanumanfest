<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Service\RegistrationTestMode;
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

    public function testDuplicatePartialEmailReturnsPayUrl(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $user = new \App\Entity\User();
        $user->setName('Доплата');
        $user->setEmail('partial-dup@example.com');
        $user->setPhone('+79001112234');
        $em->persist($user);

        $product = $option->getProduct();
        $period = $em->getRepository(PricingPeriod::class)->findOneBy(['product' => $product]);
        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(\App\Enum\ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload([]);
        $em->persist($application);
        $em->flush();

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Доплата',
                'email' => 'partial-dup@example.com',
                'phone' => '+79001112234',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('предоплата', $payload['error']);
        self::assertNotEmpty($payload['payUrl']);
        self::assertSame(1800, $payload['remainingAmount']);
        self::assertFalse($payload['cancellable']);
    }

    public function testDuplicateNewEmailReturnsPayUrl(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $body = json_encode([
            'name' => 'Первая попытка',
            'email' => 'new-dup@example.com',
            'phone' => '+79001112235',
            'participationOptionId' => $option->getId(),
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 0.5,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(409);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertNotEmpty($payload['payUrl']);
        self::assertNotEmpty($payload['token']);
        self::assertSame(3600, $payload['remainingAmount']);
        self::assertSame(1800, $payload['amountDueNow']);
        self::assertSame(0, $payload['paidAmount']);
        self::assertTrue($payload['cancellable']);
        self::assertStringContainsString('К оплате сейчас 1800', $payload['error']);
    }

    public function testPersistsAllFormFieldsForLiveAndTestApplications(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(PricingPeriod::class)->findOneBy([]);
        self::assertNotNull($product);
        self::assertNotNull($period);

        $option = new ParticipationOption();
        $option->setProduct($product);
        $option->setCode('OUR_TENT_FOOD');
        $option->setName('в нашей палатке, с питанием');
        $em->persist($option);

        $price = new ParticipationPrice();
        $price->setPricingPeriod($period);
        $price->setParticipationOption($option);
        $price->setPrice(7400);
        $em->persist($price);
        $em->flush();

        $liveBody = json_encode([
            'name' => 'Живая Анкета',
            'email' => 'form-live@example.com',
            'phone' => '89001112233',
            'participationOptionId' => $option->getId(),
            'adultsCount' => 2,
            'childrenCount' => 1,
            'transferIncluded' => true,
            'paymentFactor' => 0.5,
            'tentRoommate' => 'С сестрой Марией',
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $liveBody);
        self::assertResponseStatusCodeSame(201);
        $liveResponse = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(0.5, $liveResponse['payNowAmount'] / $liveResponse['totalAmount']);

        $em->clear();
        $live = $em->getRepository(Application::class)->findOneBy(['isTest' => false]);
        self::assertNotNull($live);
        self::assertFalse($live->isTest());
        self::assertSame('Живая Анкета', $live->getUser()?->getName());
        self::assertSame('form-live@example.com', $live->getUser()?->getEmail());
        self::assertSame('+79001112233', $live->getUser()?->getPhone());
        $livePayload = $live->getPayload();
        self::assertSame($option->getId(), $livePayload['participationOptionId']);
        self::assertSame('OUR_TENT_FOOD', $livePayload['participationOptionCode']);
        self::assertSame('в нашей палатке, с питанием', $livePayload['participationOptionName']);
        self::assertSame(2, $livePayload['adultsCount']);
        self::assertSame(1, $livePayload['childrenCount']);
        self::assertTrue($livePayload['transferIncluded']);
        self::assertEquals(0.5, $livePayload['paymentFactor']);
        self::assertSame($live->getTotalAmount() / 2, $livePayload['payNowAmount']);
        self::assertSame('С сестрой Марией', $livePayload['tentRoommate']);
        self::assertSame($livePayload['payNowAmount'], $live->getAmountDueNow());
        self::assertSame($live->getTotalAmount(), $live->getRemainingAmount());

        HanumanFestFixtures::enableRegistrationTestMode($em);
        $testOption = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => RegistrationTestMode::OPTION_CODE]);
        self::assertNotNull($testOption);

        $testBody = json_encode([
            'name' => 'Тестовая Анкета',
            'email' => 'form-test@example.com',
            'phone' => '+79001112244',
            'participationOptionId' => $testOption->getId(),
            'adultsCount' => 2,
            'childrenCount' => 1,
            'transferIncluded' => true,
            'paymentFactor' => 0.5,
            'tentRoommate' => 'С братом Иваном',
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $testBody);
        self::assertResponseStatusCodeSame(201);
        $testResponse = json_decode($client->getResponse()->getContent(), true);
        // 2*2*0.98 + 600*2 + 2*0.5 + 600 = 1804.92 → total 1805; half from raw = 902
        self::assertSame(1805, $testResponse['totalAmount']);
        self::assertSame(902, $testResponse['payNowAmount']);

        $em->clear();
        $test = $em->getRepository(Application::class)->findOneBy(['isTest' => true]);
        self::assertNotNull($test);
        self::assertTrue($test->isTest());
        self::assertSame('Тестовая Анкета', $test->getUser()?->getName());
        self::assertSame('form-test@example.com', $test->getUser()?->getEmail());
        self::assertSame('+79001112244', $test->getUser()?->getPhone());
        $testPayload = $test->getPayload();
        self::assertSame(2, $testPayload['adultsCount']);
        self::assertSame(1, $testPayload['childrenCount']);
        self::assertTrue($testPayload['transferIncluded']);
        self::assertEquals(0.5, $testPayload['paymentFactor']);
        self::assertSame(902, $testPayload['payNowAmount']);
        self::assertArrayNotHasKey('tentRoommate', $testPayload);
        self::assertSame(1805, $test->getTotalAmount());
        self::assertSame(902, $test->getAmountDueNow());
        self::assertSame(1805, $test->getRemainingAmount());

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $testBody);
        self::assertResponseStatusCodeSame(409);
        $conflict = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(902, $conflict['amountDueNow']);
        self::assertSame(1805, $conflict['remainingAmount']);
        self::assertSame(1805, $conflict['totalAmount']);
        self::assertStringContainsString('К оплате сейчас 902', $conflict['error']);
    }

    public function testCancelUnpaidApplicationAllowsReregister(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $first = json_encode([
            'name' => 'Первая заявка',
            'email' => 'cancel-rereg@example.com',
            'phone' => '+79001112236',
            'participationOptionId' => $option->getId(),
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 1,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $first);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $first);
        self::assertResponseStatusCodeSame(409);
        $conflict = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($conflict['cancellable']);
        self::assertNotEmpty($conflict['token']);

        $client->request(
            'POST',
            '/api/applications/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['token' => $conflict['token']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $second = json_encode([
            'name' => 'Другие данные',
            'email' => 'cancel-rereg@example.com',
            'phone' => '+79001112299',
            'participationOptionId' => $option->getId(),
            'adultsCount' => 2,
            'childrenCount' => 1,
            'transferIncluded' => true,
            'paymentFactor' => 0.5,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $second);
        self::assertResponseStatusCodeSame(201);

        $em->clear();
        $apps = $em->getRepository(Application::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $apps);
        self::assertSame(\App\Enum\ApplicationStatus::Cancelled, $apps[0]->getStatus());
        self::assertSame(\App\Enum\ApplicationStatus::New, $apps[1]->getStatus());
        self::assertSame(2, $apps[1]->getPayload()['adultsCount'] ?? null);
        self::assertSame('Другие данные', $apps[1]->getUser()?->getName());
    }

    public function testCancelPartialApplicationIsRejected(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $option = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD']);
        self::assertNotNull($option);

        $user = new \App\Entity\User();
        $user->setName('Доплата');
        $user->setEmail('partial-cancel@example.com');
        $user->setPhone('+79001112234');
        $em->persist($user);

        $product = $option->getProduct();
        $period = $em->getRepository(PricingPeriod::class)->findOneBy(['product' => $product]);
        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(\App\Enum\ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload([]);
        $em->persist($application);
        $em->flush();

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Доплата',
                'email' => 'partial-cancel@example.com',
                'phone' => '+79001112234',
                'participationOptionId' => $option->getId(),
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        $conflict = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($conflict['cancellable']);
        self::assertNotEmpty($conflict['token']);

        $client->request(
            'POST',
            '/api/applications/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['token' => $conflict['token']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(400);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('Нельзя отменить', $payload['error']);
    }

    public function testCancelUnknownTokenReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);

        $client->request(
            'POST',
            '/api/applications/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['token' => 'missing-token'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Заявка не найдена.', $payload['error']);
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
