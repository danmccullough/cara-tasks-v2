<?php

namespace App\Http\Controllers\Parent;

use App\Enums\OccurrenceStatus;
use App\Enums\RedemptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Occurrence;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Support\Household;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        [$start, $end] = Household::dayRange();

        $kids = User::kids()
            ->orderBy('name')
            ->with(['occurrences' => fn ($q) => $q->with('chore')
                ->where('due_at', '>=', $start)
                ->where('due_at', '<', $end)
                ->orderBy('due_at')])
            ->get()
            ->map(fn (User $kid) => [
                'id' => $kid->id,
                'name' => $kid->name,
                'avatar_emoji' => $kid->avatar_emoji,
                'color' => $kid->color,
                'points' => $kid->points_balance,
                'today_total' => $kid->occurrences->count(),
                'today_done' => $kid->occurrences->filter(fn (Occurrence $o) => $o->status->isDone())->count(),
                'today' => $kid->occurrences->map(fn (Occurrence $o) => [
                    'id' => $o->id,
                    'title' => $o->chore->title,
                    'icon' => $o->chore->icon,
                    'due_at' => $o->due_at->toIso8601String(),
                    'status' => $o->status,
                    'started_at' => $o->started_at?->toIso8601String(),
                ]),
            ]);

        $awaitingApproval = Occurrence::with(['chore:id,title,icon,points', 'kid:id,name,avatar_emoji'])
            ->where('status', OccurrenceStatus::Submitted)
            ->orderBy('submitted_at')
            ->get();

        $redemptions = RewardRedemption::with(['reward:id,title,icon', 'kid:id,name,avatar_emoji'])
            ->where('status', RedemptionStatus::Requested)
            ->oldest()
            ->get();

        return Inertia::render('Dashboard', [
            'kids' => $kids,
            'awaitingApproval' => $awaitingApproval,
            'redemptions' => $redemptions,
        ]);
    }
}
