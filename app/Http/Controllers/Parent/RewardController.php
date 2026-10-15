<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RewardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('parent/Rewards', [
            'rewards' => Reward::orderBy('cost')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Reward::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Reward $reward): RedirectResponse
    {
        $reward->update($this->validated($request));

        return back();
    }

    public function destroy(Reward $reward): RedirectResponse
    {
        $reward->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:16'],
            'cost' => ['required', 'integer', 'min:1', 'max:100000'],
            'active' => ['boolean'],
        ]);
    }
}
