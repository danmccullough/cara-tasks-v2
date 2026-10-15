<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Missed = 'missed';

    /** Counts toward a streak: the kid did it, even if a parent has not reviewed it yet. */
    public function isDone(): bool
    {
        return $this === self::Submitted || $this === self::Approved;
    }
}
