<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KidLoginController extends Controller
{
    /**
     * "Who are you?" avatar picker.
     */
    public function create(): Response
    {
        return Inertia::render('auth/KidLogin', [
            'kids' => User::kids()->orderBy('name')->get(['id', 'name', 'avatar_emoji', 'color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'kid_id' => ['required', 'integer'],
            'pin' => ['required', 'digits:4'],
        ]);

        $kid = User::kids()->find($request->integer('kid_id'));

        if (! $kid) {
            throw ValidationException::withMessages(['kid_id' => 'Pick your name.']);
        }

        $throttleKey = 'kid-login:'.$kid->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many tries. Wait '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (! $kid->pin || ! Hash::check($request->string('pin'), $kid->pin)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['pin' => 'That PIN is not right. Try again.']);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($kid, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('kid.today', absolute: false));
    }
}
