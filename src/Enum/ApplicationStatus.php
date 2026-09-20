<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ApplicationStatus: string implements TranslatableInterface
{
    case New = 'NEW';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Refunded = 'REFUNDED';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::PartiallyPaid => 'Частично оплачена',
            self::Paid => 'Оплачена',
            self::Refunded => 'Возврат',
            self::Cancelled => 'Отменена',
        };
    }
}
