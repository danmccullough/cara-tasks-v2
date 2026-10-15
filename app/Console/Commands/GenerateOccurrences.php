<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\OccurrenceGenerator;
use Illuminate\Console\Command;

class GenerateOccurrences extends Command
{
    protected $signature = 'occurrences:generate';

    protected $description = 'Create upcoming chore occurrences for every active assignment';

    public function handle(OccurrenceGenerator $generator): int
    {
        Assignment::where('active', true)->each(fn (Assignment $assignment) => $generator->sync($assignment));

        return self::SUCCESS;
    }
}
