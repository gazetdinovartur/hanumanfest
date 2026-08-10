<?php

namespace App\Tests\Integration\Command;

use App\Command\GeneratePaymentLinksCommand;
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
final class GeneratePaymentLinksCommandTest extends DatabaseTestCase
{
    public function testDryRunListsPartiallyPaidApplicationsWithoutLinks(): void
    {
        HanumanFestFixtures::seed($this->entityManager);
        $this->createPartiallyPaidApplication();
        $this->entityManager->flush();

        $command = static::getContainer()->get(GeneratePaymentLinksCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--product-slug' => 'hanuman-fest']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('links_to_create=1', $tester->getDisplay());
    }

    public function testCreatesPaymentLink(): void
    {
        HanumanFestFixtures::seed($this->entityManager);
        $application = $this->createPartiallyPaidApplication();
        $this->entityManager->flush();

        $command = static::getContainer()->get(GeneratePaymentLinksCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--product-slug' => 'hanuman-fest']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('created_links=1', $tester->getDisplay());

        $this->entityManager->refresh($application);
        self::assertCount(1, $application->getPaymentLinks());
    }

    public function testSkipsWhenLinkAlreadyExists(): void
    {
        HanumanFestFixtures::seed($this->entityManager);
        $application = $this->createPartiallyPaidApplication();
        $this->entityManager->flush();

        $command = static::getContainer()->get(GeneratePaymentLinksCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--product-slug' => 'hanuman-fest']);
        $tester->execute(['--dry-run' => true, '--product-slug' => 'hanuman-fest']);

        self::assertStringContainsString('No partially paid applications without payment links found', $tester->getDisplay());
        $this->entityManager->refresh($application);
        self::assertCount(1, $application->getPaymentLinks());
    }

    private function createPartiallyPaidApplication(): Application
    {
        $user = new User();
        $user->setName('Partial User');
        $user->setEmail('partial@test.example');
        $user->setPhone('+79160000003');
        $this->entityManager->persist($user);

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPricingPeriod($period);
        $application->setStatus(ApplicationStatus::PartiallyPaid);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload([]);
        $this->entityManager->persist($application);

        return $application;
    }
}
