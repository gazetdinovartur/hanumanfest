<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Entity\SiteSettings;
use App\Service\Content\FooterContactsParser;
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
        private readonly FooterContactsParser $contactsParser,
    ) {
    }

    public function sendPartialPaymentEmail(Application $application, PaymentLink $paymentLink): void
    {
        $user = $application->getUser();
        if (!$user) {
            return;
        }

        $payUrl = $this->paymentLinkService->publicPayUrl($paymentLink);
        $settings = $this->settings();

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Хануман Фест — вы зарегистрировались')
            ->htmlTemplate('email/payment_link.html.twig')
            ->textTemplate('email/payment_link.txt.twig')
            ->context([
                'name' => $user->getName(),
                'details' => $this->registrationDetails($application),
                'contacts' => $this->contactsParser->feedbackContacts($settings),
                'paidAmount' => $application->getPaidAmount(),
                'remainingAmount' => $application->getRemainingAmount(),
                'totalAmount' => $application->getTotalAmount(),
                'payUrl' => $payUrl,
                'logoUrl' => $this->logoUrl($settings),
                'siteUrl' => rtrim($this->frontendUrl, '/'),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function registrationDetails(Application $application): array
    {
        $user = $application->getUser();
        $payload = $application->getPayload();
        $factor = (float) ($payload['paymentFactor'] ?? 1);
        $rows = [
            ['label' => 'Имя', 'value' => $user?->getName() ?? ''],
            ['label' => 'Email', 'value' => $user?->getEmail() ?? ''],
            ['label' => 'Телефон', 'value' => $user?->getPhone() ?? ''],
            ['label' => 'Вариант участия', 'value' => trim((string) ($payload['participationOptionName'] ?? ''))],
            ['label' => 'Ценовой период', 'value' => trim((string) ($payload['pricingPeriodName'] ?? ''))],
            ['label' => 'Взрослых', 'value' => (string) max(1, (int) ($payload['adultsCount'] ?? 1))],
            ['label' => 'Детей до 16 лет', 'value' => (string) max(0, (int) ($payload['childrenCount'] ?? 0))],
            ['label' => 'Трансфер', 'value' => !empty($payload['transferIncluded']) ? 'Да' : 'Нет'],
            ['label' => 'Вариант оплаты', 'value' => $factor < 1 ? 'Предоплата 50%' : 'Полная оплата'],
            ['label' => 'С кем в палатке', 'value' => trim((string) ($payload['tentRoommate'] ?? ''))],
        ];

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['value'] !== '',
        ));
    }

    private function settings(): ?SiteSettings
    {
        return $this->entityManager->getRepository(SiteSettings::class)->findOneBy([]);
    }

    private function logoUrl(?SiteSettings $settings): string
    {
        $path = PublicUploadPath::webPath($settings?->getLogoPath()) ?? '/uploads/wp/2025/10/logo-hanuman.png';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->frontendUrl, '/').$path;
    }
}
