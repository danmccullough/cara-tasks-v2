<?php

namespace App\Http\Controllers\Parent;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('parent/Family', [
            'members' => User::orderBy('role')->orderBy('name')
                ->get(['id', 'name', 'role', 'email', 'avatar_emoji', 'color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', new Enum(Role::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_if:role,parent', 'nullable', 'email', 'max:255', 'unique:users'],
            'password' => ['required_if:role,parent', 'nullable', 'string', 'min:8'],
            'pin' => ['required_if:role,kid', 'nullable', 'digits:4'],
            'avatar_emoji' => ['nullable', 'string', 'max:16'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        if ($data['role'] === Role::Parent->value) {
            User::create([
                ...collect($data)->only('role', 'name', 'email', 'password')->all(),
                'email_verified_at' => now(),
            ]);
        } else {
            User::create(collect($data)->only('role', 'name', 'pin', 'avatar_emoji', 'color')->all());
        }

        return back();
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [Rule::requiredIf($member->isParent()), 'nullable', 'email', 'max:255', Rule::unique('users')->ignore($member)],
            'password' => ['nullable', 'string', 'min:8'],
            'pin' => ['nullable', 'digits:4'],
            'avatar_emoji' => ['nullable', 'string', 'max:16'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $fields = $member->isParent() ? ['name', 'email', 'password'] : ['name', 'pin', 'avatar_emoji', 'color'];

        // Blank password/PIN means "keep the current one".
        $member->update(collect($data)->only($fields)->reject(fn ($value, $key) => in_array($key, ['password', 'pin']) && blank($value))->all());

        return back();
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        abort_if($member->is($request->user()), 403, 'You cannot remove yourself.');

        $member->delete();

        return back();
    }
}
