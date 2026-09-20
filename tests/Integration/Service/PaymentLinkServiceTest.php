<?php

namespace App\Tests\Integration\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Service\PaymentLinkService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;

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
        self::assertFalse($link->isExpired());

        $resolved = $service->getValidLink($link->getToken());
        self::assertSame($link->getToken(), $resolved->getToken());
    }

    public function testExpiredLinkIsRejected(): void
    {
        HanumanFestFixtures::seed($this->entityManager);

        $application = $this->createPartialApplication();
        $this->entityManager->flush();

        /** @var PaymentLinkService $service */
        $service = static::getContainer()->get(PaymentLinkService::class);
        $link = $service->createForApplication($application, new \DateTimeImmutable('-1 day'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $service->getValidLink($link->getToken());
    }

    private function createPartialApplication(): Application
    {
        $user = new User();
        $user->setName('Link Test');
        $user->setEmail('link@test.example');
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
