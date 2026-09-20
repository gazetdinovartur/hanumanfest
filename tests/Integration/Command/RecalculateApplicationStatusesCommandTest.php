<?php

namespace App\Tests\Integration\Command;

use App\Command\RecalculateApplicationStatusesCommand;
use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class RecalculateApplicationStatusesCommandTest extends DatabaseTestCase
{
    public function testRecalculatesPaidAmountFromSucceededPayments(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $user = new User();
        $user->setName('Recalc User');
        $user->setEmail('recalc@test.example');
        $user->setPhone('+79160000004');
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
        $application->setPayload([]);
        $this->entityManager->persist($application);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-recalc-001');
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Succeeded);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $command = static::getContainer()->get(RecalculateApplicationStatusesCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--product-slug' => 'hanuman-fest']);

        self::assertSame(0, $tester->getStatusCode());
        $this->entityManager->refresh($application);
        self::assertSame(1800, $application->getPaidAmount());
        self::assertSame(ApplicationStatus::PartiallyPaid, $application->getStatus());
    }
}
