<?php

namespace App\Tests\Functional\Api;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Service\PaymentLinkService;
use App\Tests\Support\HanumanFestFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PaymentApiTest extends WebTestCase
{
    public function testPaymentStatusForUnknownId(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $this->bootSchema($client);

        $client->request('GET', '/api/payments/unknown-yk-id/status');

        self::assertResponseIsSuccessful();
        self::assertSame(['paid' => false], json_decode($client->getResponse()->getContent(), true));
    }

    public function testPaymentStatusForSucceededPayment(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $em = $this->bootSchema($client);

        $payment = $this->createPayment($em, PaymentStatus::Succeeded, 'yk-status-001');
        $em->flush();

        $client->request('GET', '/api/payments/'.$payment->getProviderPaymentId().'/status');

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($payload['paid']);
        self::assertSame('succeeded', $payload['status']);
        self::assertSame('pay@test.example', $payload['email']);
        self::assertSame(1800, $payload['remainingAmount']);
        self::assertNotEmpty($payload['payUrl']);
    }

    public function testPaymentLinkLookupReturnsPayUrlForPartialEmail(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $em = $this->bootSchema($client);

        $this->createApplication($em);
        $em->flush();

        $client->request(
            'POST',
            '/api/payment-links/lookup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'pay@test.example'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($payload['found']);
        self::assertSame(1800, $payload['remainingAmount']);
        self::assertNotEmpty($payload['payUrl']);
    }

    public function testPaymentLinkLookupUnknownEmailReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $this->bootSchema($client);

        $client->request(
            'POST',
            '/api/payment-links/lookup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'nobody@test.example'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(['found' => false], json_decode($client->getResponse()->getContent(), true));
    }

    public function testPaymentLinkShowReturnsApplicationSummary(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $em = $this->bootSchema($client);

        $application = $this->createApplication($em);
        $em->flush();

        /** @var PaymentLinkService $linkService */
        $linkService = $client->getContainer()->get(PaymentLinkService::class);
        $link = $linkService->createForApplication($application);

        $client->request('GET', '/api/payment-links/'.$link->getToken());

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($link->getToken(), $payload['token']);
        self::assertSame((string) $application->getUuid(), $payload['application']['uuid']);
        self::assertSame(1800, $payload['application']['remainingAmount']);
    }

    public function testYookassaWebhookIgnoresLegacyPayment(): void
    {
        $client = static::createClient();
        $this->bootSchema($client);

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);
        $this->stubYookassa($client, $yookassa);

        $client->request(
            'POST',
            '/api/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['object' => ['id' => 'wp-legacy-payment']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(['ok' => true], json_decode($client->getResponse()->getContent(), true));
    }

    public function testYookassaWebhookMarksSymfonyPaymentSucceeded(): void
    {
        $client = static::createClient();
        $em = $this->bootSchema($client);

        $payment = $this->createPayment($em, PaymentStatus::Pending, 'yk-webhook-001');
        $em->flush();

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);
        $this->stubYookassa($client, $yookassa);

        $client->request(
            'POST',
            '/api/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['object' => ['id' => 'yk-webhook-001']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $em->refresh($payment);
        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
    }

    public function testCreatePaymentRequiresValidApplication(): void
    {
        $client = static::createClient();
        $this->stubYookassa($client);
        $this->bootSchema($client);

        $client->request(
            'POST',
            '/api/payments',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['applicationUuid' => '00000000-0000-0000-0000-000000000000'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPaymentLinkPayCreatesYookassaPayment(): void
    {
        $client = static::createClient();
        $em = $this->bootSchema($client);
        $application = $this->createApplication($em);
        $em->flush();

        /** @var PaymentLinkService $linkService */
        $linkService = $client->getContainer()->get(PaymentLinkService::class);
        $link = $linkService->createForApplication($application);

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('createPayment')->willReturn(new \App\Infrastructure\Yookassa\Dto\CreatePaymentResult('yk-link-pay', 'https://yookassa.test/pay'));
        $this->stubYookassa($client, $yookassa);

        $client->request('POST', '/api/payment-links/'.$link->getToken().'/pay');

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('yk-link-pay', $payload['payment_id']);
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

    private function createApplication(\Doctrine\ORM\EntityManagerInterface $em): Application
    {
        $user = new User();
        $user->setName('Pay Test');
        $user->setEmail('pay@test.example');
        $user->setPhone('+79160000001');
        $em->persist($user);

        $product = $em->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $em->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload(['payNowAmount' => 1800]);
        $em->persist($application);

        return $application;
    }

    private function createPayment(
        \Doctrine\ORM\EntityManagerInterface $em,
        PaymentStatus $status,
        string $providerPaymentId,
    ): Payment {
        $application = $this->createApplication($em);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId($providerPaymentId);
        $payment->setAmount(1800);
        $payment->setStatus($status);
        if ($status === PaymentStatus::Succeeded) {
            $payment->setPaidAt(new \DateTimeImmutable());
        }
        $em->persist($payment);

        return $payment;
    }

    private function stubYookassa(KernelBrowser $client, ?YookassaClient $yookassa = null): void
    {
        $client->getContainer()->set(
            YookassaClient::class,
            $yookassa ?? $this->createStub(YookassaClient::class),
        );
    }
}
