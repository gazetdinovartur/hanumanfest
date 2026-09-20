<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Entity\SiteSettings;
use App\Service\Content\PublicUploadPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PaymentNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly PaymentLinkService $paymentLinkService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly string $frontendUrl,
    ) {
    }

    public function sendPartialPaymentEmail(Application $application, PaymentLink $paymentLink): void
    {
        $user = $application->getUser();
        if (!$user) {
            return;
        }

        $payUrl = $this->paymentLinkService->publicPayUrl($paymentLink);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Хануман Фест — предоплата принята')
            ->htmlTemplate('email/payment_link.html.twig')
            ->textTemplate('email/payment_link.txt.twig')
            ->context([
                'name' => $user->getName(),
                'paidAmount' => $application->getPaidAmount(),
                'remainingAmount' => $application->getRemainingAmount(),
                'totalAmount' => $application->getTotalAmount(),
                'payUrl' => $payUrl,
                'logoUrl' => $this->logoUrl(),
                'siteUrl' => rtrim($this->frontendUrl, '/'),
            ]);

        $this->mailer->send($email);
    }

    private function logoUrl(): string
    {
        $settings = $this->entityManager->getRepository(SiteSettings::class)->findOneBy([]);
        $path = PublicUploadPath::webPath($settings?->getLogoPath()) ?? '/uploads/wp/2025/10/logo-hanuman.png';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->frontendUrl, '/').$path;
    }
}
