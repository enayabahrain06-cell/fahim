<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        User::create($data);

        return redirect()->route('admin.users')->with('status', 'User added.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'password' => ['nullable', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return redirect()->route('admin.users')->withErrors(['users' => 'You can’t make your own account view-only.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users')->withErrors(['users' => 'You can’t delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users')->with('status', 'User deleted.');
    }
}
