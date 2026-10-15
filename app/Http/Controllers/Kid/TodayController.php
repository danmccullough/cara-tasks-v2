<?php

namespace App\Http\Controllers\Kid;

use App\Enums\OccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Models\Occurrence;
use App\Services\StreakService;
use App\Support\Household;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(Request $request, StreakService $streaks): Response
    {
        $kid = $request->user();
        [$start, $end] = Household::dayRange();

        $occurrences = $kid->occurrences()
            ->with(['chore', 'assignment'])
            ->where('due_at', '>=', $start)
            ->where('due_at', '<', $end)
            ->where('status', '!=', OccurrenceStatus::Missed) // no "you failed" items on the kid's screen
            ->orderBy('due_at')
            ->get()
            ->map(fn (Occurrence $o) => [
                'id' => $o->id,
                'title' => $o->chore->title,
                'icon' => $o->chore->icon,
                'instructions' => $o->chore->instructions,
                'points' => $o->chore->points,
                'due_at' => $o->due_at->toIso8601String(),
                'remind_at' => $o->due_at->subMinutes($o->assignment->remind_before_min)->toIso8601String(),
                'status' => $o->status,
                'review_note' => $o->review_note,
            ]);

        return Inertia::render('kid/Today', [
            'occurrences' => $occurrences,
            'points' => $kid->points_balance,
            'streak' => $streaks->current($kid),
        ]);
    }
}
