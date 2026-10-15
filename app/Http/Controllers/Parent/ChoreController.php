<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Chore;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('parent/Chores', [
            'chores' => Chore::with('assignments')->orderBy('title')->get(),
            'kids' => User::kids()->orderBy('name')->get(['id', 'name', 'avatar_emoji', 'color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Chore::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Chore $chore): RedirectResponse
    {
        $chore->update($this->validated($request));

        return back();
    }

    public function destroy(Chore $chore): RedirectResponse
    {
        $chore->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:16'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'active' => ['boolean'],
        ]);
    }
}
