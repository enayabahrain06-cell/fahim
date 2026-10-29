<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function show(): View
    {
        return view('admin.setup');
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
