<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('role')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['role' => User::ROLE_OFFICER])]);
    }

    public function store(UserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $temporary = Str::password(12, symbols: false);

        $user = User::create($request->validated() + [
            'password' => $temporary,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $audit->log('user.created', $user, null, $user->only(['name', 'email', 'role', 'position']));

        return redirect()->route('users.index')
            ->with('success', "Account created for {$user->name}.")
            ->with('temp_password', $temporary)
            ->with('temp_for', $user->email);
    }

    public function edit(User $user): View
    {
        return view('users.form', compact('user'));
    }

    public function update(UserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();

        // Admins cannot demote themselves (prevents locking everyone out).
        if ($user->is($request->user())) {
            unset($data['role']);
        }

        $user->fill($data);
        $audit->saveWithAudit($user, 'user.updated');

        return redirect()->route('users.index')->with('success', 'Account updated.');
    }

    public function toggle(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->is_active = ! $user->is_active;
        $audit->saveWithAudit($user, $user->is_active ? 'user.activated' : 'user.deactivated');

        return back()->with('success', $user->is_active ? 'Account activated.' : 'Account deactivated. They can no longer sign in.');
    }

    public function resetPassword(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Use "Change password" from your own account menu instead.');
        }

        $temporary = Str::password(12, symbols: false);
        $user->forceFill(['password' => $temporary, 'must_change_password' => true])->save();

        $audit->log('user.password_reset', $user); // the password itself is never logged

        return back()
            ->with('success', "Temporary password generated for {$user->name}.")
            ->with('temp_password', $temporary)
            ->with('temp_for', $user->email);
    }
}
