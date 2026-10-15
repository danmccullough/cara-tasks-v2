<?php

namespace App\Enums;

enum Role: string
{
    case Parent = 'parent';
    case Kid = 'kid';
}
