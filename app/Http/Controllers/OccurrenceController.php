<?php

namespace App\Http\Controllers;

use App\Enums\OccurrenceStatus;
use App\Enums\PointReason;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreApproved;
use App\Notifications\ChoreSubmitted;
use App\Services\PointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OccurrenceController extends Controller
{
    /** Kid taps "Done". */
    public function submit(Request $request, Occurrence $occurrence): RedirectResponse
    {
        abort_unless($occurrence->kid_id === $request->user()->id, 403);
        abort_unless($occurrence->status === OccurrenceStatus::Pending, 409);

        $occurrence->update([
            'status' => OccurrenceStatus::Submitted,
            'submitted_at' => now(),
        ]);

        Notification::send(User::parents()->get(), new ChoreSubmitted($occurrence));

        return back();
    }

    public function approve(Request $request, Occurrence $occurrence, PointsService $points): RedirectResponse
    {
        DB::transaction(function () use ($request, $occurrence, $points) {
            // Lock so a double-click can't award points twice.
            $occurrence = Occurrence::lockForUpdate()->findOrFail($occurrence->id);
            abort_unless($occurrence->status === OccurrenceStatus::Submitted, 409);

            $occurrence->update([
                'status' => OccurrenceStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $points->record($occurrence->kid, $occurrence->chore->points, PointReason::Chore, $occurrence);

            $occurrence->kid->notify(new ChoreApproved($occurrence));
        });

        return back();
    }

    /** Send it back to the kid; reminders resume because it is pending again. */
    public function reject(Request $request, Occurrence $occurrence): RedirectResponse
    {
        abort_unless($occurrence->status === OccurrenceStatus::Submitted, 409);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $occurrence->update([
            'status' => OccurrenceStatus::Pending,
            'submitted_at' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
            'reminders_sent' => 0, // start reminding again right away
        ]);

        return back();
    }
}
