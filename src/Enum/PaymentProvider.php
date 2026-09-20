<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum PaymentProvider: string implements TranslatableInterface
{
    case Yookassa = 'YOOKASSA';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return 'ЮKassa';
    }
}
