<?php

namespace App\Http\Controllers;

use App\Actions\ClaimGuestBookings;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, ClaimGuestBookings $claimGuestBookings): RedirectResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'These login details do not match an account.']);
        }
        $request->session()->regenerate();

        $claimGuestBookings->handle($request->user(), $request->session()->get('player_key'));

        return redirect()->intended(route($request->user()->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
