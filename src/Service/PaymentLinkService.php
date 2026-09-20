<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\PaymentLink;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentLinkService
{
    public const STATE_PAYABLE = 'payable';
    public const STATE_PAID = 'paid';
    public const STATE_UNAVAILABLE = 'unavailable';
    public const STATE_INVALID = 'invalid';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApplicationRepository $applicationRepository,
        private readonly string $frontendUrl,
    ) {
    }

    public function createForApplication(Application $application): PaymentLink
    {
        $paymentLink = new PaymentLink();
        $application->addPaymentLink($paymentLink);

        $this->entityManager->persist($paymentLink);
        $this->entityManager->flush();

        return $paymentLink;
    }

    public function findByToken(string $token): ?PaymentLink
    {
        return $this->entityManager->getRepository(PaymentLink::class)->findOneBy(['token' => $token]);
    }

    public function findForApplication(Application $application): ?PaymentLink
    {
        return $this->entityManager->getRepository(PaymentLink::class)->findOneBy(
            ['application' => $application],
            ['id' => 'ASC'],
        );
    }

    public function ensureForPartialApplication(Application $application): ?PaymentLink
    {
        $existing = $this->findForApplication($application);
        if ($existing instanceof PaymentLink) {
            return $existing;
        }

        if (
            $application->getRemainingAmount() <= 0
            || !\in_array($application->getStatus(), [ApplicationStatus::New, ApplicationStatus::PartiallyPaid], true)
        ) {
            return null;
        }

        return $this->createForApplication($application);
    }

    public function getValidLink(string $token): PaymentLink
    {
        $paymentLink = $this->findByToken($token);
        $state = $this->state($paymentLink);

        if ($state === self::STATE_PAID) {
            throw new BadRequestHttpException('Application is already fully paid');
        }

        if ($state !== self::STATE_PAYABLE || !$paymentLink) {
            throw new NotFoundHttpException('Payment link not found.');
        }

        return $paymentLink;
    }

    public function state(?PaymentLink $paymentLink): string
    {
        if (!$paymentLink) {
            return self::STATE_INVALID;
        }

        $application = $paymentLink->getApplication();
        if (!$application) {
            return self::STATE_INVALID;
        }

        if (\in_array($application->getStatus(), [ApplicationStatus::Cancelled, ApplicationStatus::Refunded], true)) {
            return self::STATE_UNAVAILABLE;
        }

        if ($application->getRemainingAmount() <= 0 || $application->getStatus() === ApplicationStatus::Paid) {
            return self::STATE_PAID;
        }

        if (
            $application->getRemainingAmount() > 0
            && \in_array($application->getStatus(), [ApplicationStatus::New, ApplicationStatus::PartiallyPaid], true)
        ) {
            return self::STATE_PAYABLE;
        }

        return self::STATE_UNAVAILABLE;
    }

    public function publicPayUrl(PaymentLink $paymentLink): string
    {
        return rtrim($this->frontendUrl, '/').'/pay/'.$paymentLink->getToken();
    }

    /**
     * @return array{
     *     found: true,
     *     name: ?string,
     *     paidAmount: int,
     *     remainingAmount: int,
     *     amountDueNow: int,
     *     totalAmount: int,
     *     payUrl: string,
     *     token: string,
     *     cancellable: bool
     * }|null
     */
    public function lookupPartialPayment(string $email, bool $isTest = false): ?array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $application = $this->applicationRepository->findPayableByEmail($email, $isTest);
        if (!$application) {
            return null;
        }

        $link = $this->ensureForPartialApplication($application);
        if (!$link instanceof PaymentLink) {
            return null;
        }

        return [
            'found' => true,
            'name' => $application->getUser()?->getName(),
            'paidAmount' => $application->getPaidAmount(),
            'remainingAmount' => $application->getRemainingAmount(),
            'amountDueNow' => $application->getAmountDueNow(),
            'totalAmount' => $application->getTotalAmount(),
            'payUrl' => $this->publicPayUrl($link),
            'token' => $link->getToken(),
            'cancellable' => $application->getPaidAmount() === 0
                && $application->getStatus() === ApplicationStatus::New,
        ];
    }
}
