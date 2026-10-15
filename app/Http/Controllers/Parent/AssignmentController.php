<?php

namespace App\Http\Controllers\Parent;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\OccurrenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function __construct(private OccurrenceGenerator $generator) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chore_id' => ['required', 'exists:chores,id'],
            'kid_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Kid->value)],
            ...$this->scheduleRules(),
        ]);

        $this->generator->sync(Assignment::create($data));

        return back();
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $assignment->update($request->validate($this->scheduleRules()));

        $this->generator->sync($assignment);

        return back();
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return back();
    }

    private function scheduleRules(): array
    {
        return [
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7', 'distinct'],
            'due_time' => ['required', 'date_format:H:i'],
            'remind_before_min' => ['integer', 'between:0,240'],
            'nag_every_min' => ['integer', 'between:1,120'],
            'max_nags' => ['integer', 'between:0,20'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['boolean'],
        ];
    }
}
