<?php

namespace App\Tests\Integration\Service;

use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Service\PaymentLinkService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('integration')]
final class PaymentLinkServiceTest extends DatabaseTestCase
{
    public function testCreateAndResolveValidLink(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication();
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $link = $service->createForApplication($application);

        self::assertNotSame('', $link->getToken());
        self::assertSame(PaymentLinkService::STATE_PAYABLE, $service->state($link));

        $resolved = $service->getValidLink($link->getToken());
        self::assertSame($link->getToken(), $resolved->getToken());
        self::assertStringContainsString('/pay/'.$link->getToken(), $service->publicPayUrl($link));
    }

    public function testDatedLinkRemainsValidUntilPaidOrCancelled(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication();
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $link = $service->createForApplication($application, new \DateTimeImmutable('-1 day'));

        $resolved = $service->getValidLink($link->getToken());
        self::assertSame($link->getToken(), $resolved->getToken());
    }

    public function testPaidLinkIsRejected(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication();
        $application->setPaidAmount(3600);
        $application->setStatus(ApplicationStatus::Paid);
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $link = $service->createForApplication($application);

        $this->expectException(BadRequestHttpException::class);
        $service->getValidLink($link->getToken());
    }

    public function testCancelledLinkIsRejected(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication();
        $application->setStatus(ApplicationStatus::Cancelled);
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $link = $service->createForApplication($application);

        $this->expectException(NotFoundHttpException::class);
        $service->getValidLink($link->getToken());
    }

    public function testLookupFindsPartialPaymentByEmail(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication('lookup@test.example');
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $result = $service->lookupPartialPayment('Lookup@test.example');

        self::assertNotNull($result);
        self::assertTrue($result['found']);
        self::assertSame(1800, $result['remainingAmount']);
        self::assertNotSame('', $result['payUrl']);
        self::assertNotNull($service->findForApplication($application));
    }

    private function createPartialApplication(string $email = 'link@test.example'): Application
    {
        $user = new User();
        $user->setName('Link Test');
        $user->setEmail($email);
        $user->setPhone('+79160000002');
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

        return $application;
    }
}
