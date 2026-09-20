<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PaymentNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
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

        $remaining = max(0, $application->getTotalAmount() - $application->getPaidAmount());
        $payUrl = rtrim($this->frontendUrl, '/').'/pay/'.$paymentLink->getToken();

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Хануман Фест — оплата остатка')
            ->htmlTemplate('email/payment_link.html.twig')
            ->context([
                'name' => $user->getName(),
                'paidAmount' => $application->getPaidAmount(),
                'remainingAmount' => $remaining,
                'totalAmount' => $application->getTotalAmount(),
                'payUrl' => $payUrl,
            ]);

        $this->mailer->send($email);
    }
}
