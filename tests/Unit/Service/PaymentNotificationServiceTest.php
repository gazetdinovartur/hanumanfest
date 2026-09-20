<?php

namespace App\Tests\Unit\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Entity\SiteSettings;
use App\Entity\User;
use App\Service\Content\FooterContactsParser;
use App\Service\PaymentLinkService;
use App\Service\PaymentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
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
        $user->setPhone('+79160000000');

        $application = new Application();
        $application->setUser($user);
        $application->setTotalAmount(3600);
        $application->setPaidAmount(1800);
        $application->setPayload([
            'participationOptionName' => 'Палатка',
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 0.5,
        ]);

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

        $settings = new SiteSettings();
        $settings->setContactsHtml("hanumanfest@gmail.com\nhttps://t.me/Hanuman_ekb");
        $settingsRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $settingsRepository->method('findOneBy')->willReturn($settings);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($settingsRepository);

        $service = new PaymentNotificationService(
            $mailer,
            $paymentLinkService,
            $entityManager,
            'noreply@hanumanfest.ru',
            'Хануман Фест',
            'https://хануманфест.рф',
            new FooterContactsParser(),
        );
        $service->sendPartialPaymentEmail($application, $link);

        self::assertInstanceOf(TemplatedEmail::class, $sent);
        self::assertSame('Хануман Фест — вы зарегистрировались', $sent->getSubject());
        self::assertSame(['notify@example.com'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
        $context = $sent->getContext();
        self::assertSame('Тест', $context['details'][0]['value']);
        self::assertSame('Палатка', $context['details'][3]['value']);
        self::assertNotSame([], $context['contacts']);
    }
}
