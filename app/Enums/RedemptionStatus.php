<?php

namespace App\Enums;

enum RedemptionStatus: string
{
    case Requested = 'requested';
    case Fulfilled = 'fulfilled';
    case Denied = 'denied';
}
