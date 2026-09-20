<?php

namespace App\Tests\Unit\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Entity\User;
use App\Service\PaymentLinkService;
use App\Service\PaymentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Group('unit')]
final class PaymentNotificationServiceTest extends TestCase
{
    public function testSendsPartialPaymentEmailInRussian(): void
    {
        $user = new User();
        $user->setName('Тест');
        $user->setEmail('notify@example.com');

        $application = new Application();
        $application->setUser($user);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);

        $link = new PaymentLink();
        $link->setToken('test-token-abc');

        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sent): void {
                $sent = $email;
            });

        $paymentLinkService = $this->createMock(PaymentLinkService::class);
        $paymentLinkService->method('publicPayUrl')
            ->willReturn('https://хануманфест.рф/pay/test-token-abc');

        $settingsRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $settingsRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($settingsRepository);

        $service = new PaymentNotificationService(
            $mailer,
            $paymentLinkService,
            $entityManager,
            'noreply@hanumanfest.ru',
            'Хануман Фест',
            'https://хануманфест.рф',
        );
        $service->sendPartialPaymentEmail($application, $link);

        self::assertNotNull($sent);
        self::assertSame('Хануман Фест — предоплата принята', $sent->getSubject());
        self::assertSame(['notify@example.com'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
    }
}
