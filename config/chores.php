<?php

return [

    /*
    | The household's local timezone. Assignment times ("19:00 on weekdays")
    | are interpreted here; everything is stored in UTC.
    */
    'timezone' => env('HOUSEHOLD_TIMEZONE', 'America/New_York'),

    // How many days ahead occurrences are generated.
    'generate_days_ahead' => (int) env('CHORES_GENERATE_DAYS', 7),

    // Minutes after the due time before a still-pending chore is marked missed.
    'grace_min' => (int) env('CHORES_GRACE_MIN', 120),

];
