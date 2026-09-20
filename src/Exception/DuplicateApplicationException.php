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

    public function getPaidAmount(): int
    {
        return $this->application->getPaidAmount();
    }

    public function getRemainingAmount(): int
    {
        return $this->application->getRemainingAmount();
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
                'По этому email уже есть предоплата. Осталось оплатить %d ₽ — ссылка ниже и в письме.',
                $remaining,
            );
        }

        if ($application->getStatus() === ApplicationStatus::Paid) {
            return 'По этому email регистрация уже полностью оплачена.';
        }

        return 'По этому email уже есть активная заявка.';
    }
}
