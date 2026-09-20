<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\Payment;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentStatus;
use App\Infrastructure\Yookassa\Dto\CreatePaymentResult;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RegistrationFlowApiTest extends WebTestCase
{
    public function testFullRegistrationPaymentAndWebhookFlow(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->stubYookassa($client, $this->createConfiguredYookassaMock());
        $em = $this->bootSchema($client);
        $optionId = $em->getRepository(\App\Entity\ParticipationOption::class)->findOneBy([])?->getId();
        self::assertNotNull($optionId);

        $client->request(
            'POST',
            '/api/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'participationOptionId' => $optionId,
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $calculate = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1800, $calculate['payNowAmount']);

        $client->request(
            'POST',
            '/api/applications',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'E2E Участник',
                'email' => 'e2e-flow@example.com',
                'phone' => '+79001112233',
                'participationOptionId' => $optionId,
                'adultsCount' => 1,
                'childrenCount' => 0,
                'transferIncluded' => false,
                'paymentFactor' => 0.5,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $applicationPayload = json_decode($client->getResponse()->getContent(), true);
        $applicationUuid = $applicationPayload['uuid'] ?? null;
        self::assertNotEmpty($applicationUuid);

        $client->request(
            'POST',
            '/api/payments',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['applicationUuid' => $applicationUuid], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $paymentPayload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('yk-e2e-001', $paymentPayload['payment_id']);

        $client->request(
            'POST',
            '/api/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['object' => ['id' => 'yk-e2e-001']], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/payments/yk-e2e-001/status');
        self::assertResponseIsSuccessful();
        $statusPayload = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($statusPayload['paid']);

        $application = $em->getRepository(Application::class)->findOneBy([]);
        self::assertNotNull($application);
        self::assertSame(ApplicationStatus::PartiallyPaid, $application->getStatus());
        self::assertSame(1800, $application->getPaidAmount());

        $payment = $em->getRepository(Payment::class)->findOneBy(['providerPaymentId' => 'yk-e2e-001']);
        self::assertNotNull($payment);
        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
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

    private function stubYookassa(KernelBrowser $client, YookassaClient $yookassa): void
    {
        $client->getContainer()->set(YookassaClient::class, $yookassa);
    }

    private function createConfiguredYookassaMock(): YookassaClient
    {
        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('createPayment')->willReturn(new CreatePaymentResult('yk-e2e-001', 'https://yookassa.test/pay'));
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);

        return $yookassa;
    }
}
