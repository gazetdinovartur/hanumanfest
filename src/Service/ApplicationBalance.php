<?php

namespace App\Service;

use App\Entity\Application;
use App\Enum\ApplicationStatus;

final class ApplicationBalance
{
    public static function resolveStatus(int $paidAmount, int $totalAmount, int $refundedAmount): ApplicationStatus
    {
        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return ApplicationStatus::Paid;
        }

        if ($paidAmount > 0) {
            return ApplicationStatus::PartiallyPaid;
        }

        if ($refundedAmount > 0) {
            return ApplicationStatus::Refunded;
        }

        return ApplicationStatus::New;
    }

    public static function applyToApplication(Application $application, int $paidAmount, int $refundedAmount): void
    {
        $application->setPaidAmount(max(0, $paidAmount));
        $application->setStatus(self::resolveStatus(
            $application->getPaidAmount(),
            $application->getTotalAmount(),
            max(0, $refundedAmount),
        ));
    }
}
