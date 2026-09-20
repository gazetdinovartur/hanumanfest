<?php

namespace App\Tests\Integration\Service;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Service\PaymentService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class PaymentServiceTest extends DatabaseTestCase
{
    public function testMarkPaymentSucceededCreatesPartialPaymentLink(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $user = new User();
        $user->setName('Test User');
        $user->setEmail('pay@test.example');
        $user->setPhone('+79160000001');
        $this->entityManager->persist($user);

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(0);
        $application->setPayload(['payNowAmount' => 1800]);
        $this->entityManager->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-test-001');
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Pending);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);
        static::getContainer()->set(YookassaClient::class, $yookassa);

        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);
        $paymentService->handleYookassaWebhook(['object' => ['id' => 'yk-test-001']]);

        $this->entityManager->refresh($application);
        $this->entityManager->refresh($payment);

        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
        self::assertSame(1800, $application->getPaidAmount());
        self::assertSame(ApplicationStatus::PartiallyPaid, $application->getStatus());
        self::assertCount(1, $application->getPaymentLinks());
    }

    public function testRefundWebhookReducesPaidAmount(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $user = new User();
        $user->setName('Refund User');
        $user->setEmail('refund@test.example');
        $user->setPhone('+79160000009');
        $this->entityManager->persist($user);

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::Paid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(3600);
        $application->setPayload([]);
        $this->entityManager->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-refund-live');
        $payment->setAmount(3600);
        $payment->setStatus(PaymentStatus::Succeeded);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('verifyPayment')->willReturn([
            'status' => 'succeeded',
            'refunded_amount' => ['value' => '3600.00', 'currency' => 'RUB'],
        ]);
        static::getContainer()->set(YookassaClient::class, $yookassa);

        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);
        $paymentService->handleYookassaWebhook([
            'event' => 'refund.succeeded',
            'object' => [
                'id' => 'refund-1',
                'payment_id' => 'yk-refund-live',
            ],
        ]);

        $this->entityManager->refresh($application);
        $this->entityManager->refresh($payment);

        self::assertSame(3600, $payment->getRefundedAmount());
        self::assertSame(0, $application->getPaidAmount());
        self::assertSame(ApplicationStatus::Refunded, $application->getStatus());
    }

    public function testGetPaymentStatusForUnknownPayment(): void
    {
        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);

        self::assertSame(['paid' => false], $paymentService->getPaymentStatus('missing-id'));
    }

    public function testGetPaymentStatusReturnsPayUrlForPartialPayment(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $user = new User();
        $user->setName('Status User');
        $user->setEmail('status@test.example');
        $user->setPhone('+79160000011');
        $this->entityManager->persist($user);

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload([]);
        $this->entityManager->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-status-partial');
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Succeeded);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);
        $status = $paymentService->getPaymentStatus('yk-status-partial');

        self::assertTrue($status['paid']);
        self::assertSame(1800, $status['remainingAmount']);
        self::assertNotEmpty($status['payUrl']);
        self::assertCount(1, $application->getPaymentLinks());
    }

    public function testGetPaymentStatusSyncsPendingFromYookassa(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $user = new User();
        $user->setName('Pending User');
        $user->setEmail('pending@test.example');
        $user->setPhone('+79160000012');
        $this->entityManager->persist($user);

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setSeason($period?->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(0);
        $application->setPayload(['payNowAmount' => 1800]);
        $this->entityManager->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-pending-sync');
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Pending);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $yookassa = $this->createMock(YookassaClient::class);
        $yookassa->method('verifyPayment')->willReturn(['status' => 'succeeded']);
        static::getContainer()->set(YookassaClient::class, $yookassa);

        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);
        $status = $paymentService->getPaymentStatus('yk-pending-sync');

        $this->entityManager->refresh($application);
        $this->entityManager->refresh($payment);

        self::assertTrue($status['paid']);
        self::assertSame(ApplicationStatus::PartiallyPaid, $application->getStatus());
        self::assertNotEmpty($status['payUrl']);
        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
    }
}
