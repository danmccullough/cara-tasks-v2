<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:parent') or ->middleware('role:kid').
 * Users with the wrong role are sent to their own home page instead of an error.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user->role->value !== $role) {
            return redirect()->route($user->isKid() ? 'kid.today' : 'dashboard');
        }

        return $next($request);
    }
}
