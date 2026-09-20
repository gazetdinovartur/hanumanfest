<?php

namespace App\Exception;

use App\Entity\Application;
use App\Enum\ApplicationStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DuplicateApplicationException extends ConflictHttpException
{
    public function __construct(
        private readonly Application $application,
        private readonly ?string $payUrl,
        private readonly ?string $paymentToken = null,
    ) {
        parent::__construct(self::messageFor($application));
    }

    public function getApplication(): Application
    {
        return $this->application;
    }

    public function getPayUrl(): ?string
    {
        return $this->payUrl;
    }

    public function getPaymentToken(): ?string
    {
        return $this->paymentToken;
    }

    public function isCancellable(): bool
    {
        return $this->application->getPaidAmount() === 0
            && $this->application->getStatus() === ApplicationStatus::New
            && $this->paymentToken !== null
            && $this->paymentToken !== '';
    }

    public function getPaidAmount(): int
    {
        return $this->application->getPaidAmount();
    }

    public function getRemainingAmount(): int
    {
        return $this->application->getRemainingAmount();
    }

    public function getAmountDueNow(): int
    {
        return $this->application->getAmountDueNow();
    }

    public function getTotalAmount(): int
    {
        return $this->application->getTotalAmount();
    }

    private static function messageFor(Application $application): string
    {
        $remaining = $application->getRemainingAmount();
        if ($application->getStatus() === ApplicationStatus::PartiallyPaid && $remaining > 0) {
            return sprintf(
                'По этому email уже есть предоплата. Осталось оплатить %d ₽.',
                $remaining,
            );
        }

        if ($application->getStatus() === ApplicationStatus::Paid) {
            return 'По этому email регистрация уже полностью оплачена.';
        }

        if ($remaining > 0) {
            return sprintf(
                'По этому email уже есть заявка. К оплате сейчас %d ₽. Анкету заполнять заново не нужно.',
                $application->getAmountDueNow(),
            );
        }

        return 'По этому email уже есть активная заявка.';
    }
}
