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

    public function testGetPaymentStatusForUnknownPayment(): void
    {
        /** @var PaymentService $paymentService */
        $paymentService = static::getContainer()->get(PaymentService::class);

        self::assertSame(['paid' => false], $paymentService->getPaymentStatus('missing-id'));
    }
}
