<?php

namespace App\Enum;

enum ApplicationStatus: string
{
    case New = 'NEW';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Refunded = 'REFUNDED';
}
