<?php

namespace App\Enums;

enum PointReason: string
{
    case Chore = 'chore';
    case Reward = 'reward';
    case Adjustment = 'adjustment';
}
