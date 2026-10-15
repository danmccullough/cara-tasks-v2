<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Converts the household's local calendar days into UTC ranges for querying.
 */
class Household
{
    public static function timezone(): string
    {
        return config('chores.timezone');
    }

    /** Start of today in household time. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    /**
     * UTC [start, end) bounds for a local day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayRange(?CarbonImmutable $localDay = null): array
    {
        $start = ($localDay ?? self::today())->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }
}
