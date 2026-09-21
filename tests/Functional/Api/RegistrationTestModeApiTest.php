<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\ParticipationOption;
use App\Enum\ApplicationStatus;
use App\Infrastructure\Yookassa\Dto\CreatePaymentResult;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Service\RegistrationTestMode;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RegistrationTestModeApiTest extends WebTestCase
{
    public function testTestOptionUsesFormulaAndDoesNotForceAllPrices(): void
    {
        $client = static::createClient();
        $em = $this->bootSchema($client);
        $liveOptionId = $em->getRepository(ParticipationOption::class)->findOneBy(['code' => 'OWN_HOUSE_NO_FOOD'])?->getId();
        self::assertNotNull($liveOptionId);

        $client->request(
            'POST',
            '/api/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'participationOptionId' => $liveOptionId,
                'adultsCount' => 1,
                'paymentFactor' => 1,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $off = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(3600, $off['totalAmount']);

        $testOption = HanumanFestFixtures::enableRegistrationTestMode($em);
        $testOptionId = $testOption->getId();
        self::assertNotNull($testOptionId);

        $client->request('GET', '/api/product');
        self::assertResponseIsSuccessful();
        $product = json_decode($client->getResponse()->getContent(), true);
        $codes = array_column($product['participationOptions'], 'code');
        self::assertContains(RegistrationTestMode::OPTION_CODE, $codes);
        self::assertContains('OWN_HOUSE_NO_FOOD', $codes);

        $client->request(
            'POST',
            '/api/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'participationOptionId' => $liveOptionId,
                'adultsCount' => 3,
                'childrenCount' => 2,
                'transferIncluded' => true,
                'paymentFactor' => 1,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $liveWhileTestMode = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(17076, $liveWhileTestMode['totalAmount']);

        $client->request(
            'POST',
            '/api/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'participationOptionId' => $testOptionId,
                'adultsCount' => 2,
                'childrenCount' => 1,
                'transferIncluded' => true,
                'paymentFactor' => 1,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $testCalc = json_decode($client->getResponse()->getContent(), true);
        // 2*2*0.98 + 600*2 + 2*0.5 + 600 = 1805
        self::assertSame(1805, $testCalc['totalAmount']);
        self::assertSame(1805, $testCalc['payNowAmount']);
        self::assertSame(RegistrationTestMode::OPTION_NAME, $testCalc['participationOptionName']);

        $body = json_encode([
            'name' => 'Тест Режим',
            'email' => 'test-mode@example.com',
            'phone' => '+79001112233',
            'participationOptionId' => $testOptionId,
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 1,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $created['totalAmount']);
        self::assertSame(2, $created['payNowAmount']);

        $testApplication = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($testApplication);
        self::assertTrue($testApplication->isTest());
        self::assertEquals(1.0, $testApplication->getPayload()['paymentFactor']);

        $em->getRepository(\App\Entity\SiteSettings::class)->findOneBy([])?->setRegistrationTestMode(false);
        $em->flush();

        $client->request('GET', '/api/product');
        $codesOff = array_column(json_decode($client->getResponse()->getContent(), true)['participationOptions'], 'code');
        self::assertNotContains(RegistrationTestMode::OPTION_CODE, $codesOff);

        $liveBody = json_encode([
            'name' => 'Тест Режим',
            'email' => 'test-mode@example.com',
            'phone' => '+79001112233',
            'participationOptionId' => $liveOptionId,
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 1,
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/applications', server: ['CONTENT_TYPE' => 'application/json'], content: $liveBody);
        self::assertResponseStatusCodeSame(201);
        $applications = $em->getRepository(Application::class)->findAll();
        self::assertCount(2, $applications);
        $real = array_values(array_filter($applications, static fn (Application $app): bool => !$app->isTest()))[0] ?? null;
        self::assertNotNull($real);
        self::assertFalse($real->isTest());
        self::assertSame(3600, $real->getTotalAmount());
    }

    public function testTwoStepPaymentsOnePlusOneMarkApplicationPaid(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->bootSchema($client);
        $testOption = HanumanFestFixtures::enableRegistrationTestMode($em);
        $optionId = $testOption->getId();
        self::assertNotNull($optionId);

        $charged = [];
        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->expects(self::exactly(2))
            ->method('createPayment')
            ->willReturnCallback(static function (string $email, string $phone, int $amount) use (&$charged): CreatePaymentResult {
                $charged[] = $amount;
                $n = count($charged);

                return new CreatePaymentResult('yk-test-'.$n, 'https://yookassa.test/pay-'.$n);
            });
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);
        $client->getContainer()->set(YookassaClient::class, $yookassa);

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Две Оплаты',
                'email' => 'two-pay@example.com',
                'phone' => '+79002223344',
                'participationOptionId' => $optionId,
                'adultsCount' => 1,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(201, $client->getResponse()->getStatusCode());
        $created = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $created['totalAmount']);
        self::assertSame(1, $created['payNowAmount']);
        $uuid = $created['uuid'] ?? '';

        $client->request(
            'POST',
            '/api/payments',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['applicationUuid' => $uuid], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['object' => ['id' => 'yk-test-1']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $em->clear();
        $application = $em->getRepository(Application::class)->findOneBy(['uuid' => $uuid]);
        self::assertNotNull($application);
        self::assertSame(ApplicationStatus::PartiallyPaid, $application->getStatus());
        self::assertSame(1, $application->getPaidAmount());
        self::assertCount(1, $application->getPaymentLinks());
        $token = $application->getPaymentLinks()->first()->getToken();

        $client->request('POST', '/api/payment-links/'.$token.'/pay');
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['object' => ['id' => 'yk-test-2']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $em->clear();
        $application = $em->getRepository(Application::class)->findOneBy(['uuid' => $uuid]);
        self::assertNotNull($application);
        self::assertSame(2, $application->getPaidAmount());
        self::assertSame(ApplicationStatus::Paid, $application->getStatus());
        self::assertSame([1, 1], $charged);
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
}
