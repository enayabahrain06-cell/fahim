<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! User::query()->exists()) {
            return redirect()->route('admin.setup');
        }
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $login = trim($request->input('email'));
        $field = str_contains($login, '@') ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $request->input('password')], $request->boolean('remember'))) {
            ActivityLog::record('Failed sign-in', $login, null, $login);
            throw ValidationException::withMessages(['email' => 'That username or email and password don’t match.']);
        }

        $request->session()->regenerate();
        ActivityLog::record('Signed in', null, $request->user());

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::record('Signed out', null, $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
