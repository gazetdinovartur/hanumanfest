<?php

namespace App\Enum;

enum PersonKind: string
{
    case Guest = 'guest';
    case Musician = 'musician';
    case Master = 'master';
}
