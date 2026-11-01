<?php

namespace App\Http\Controllers;

use App\Actions\ClaimGuestBookings;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PlayerAccountController extends Controller
{
    public function create(): View
    {
        return view('play.register');
    }

    public function store(Request $request, ClaimGuestBookings $claimGuestBookings): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = User::query()->create($data);
        Auth::login($user);
        $request->session()->regenerate();
        rescue(fn () => event(new Registered($user)));
        $claimed = $claimGuestBookings->handle($user, $request->session()->get('player_key'));

        return redirect()->intended(route('play.dashboard'))->with('success', $claimed > 0 ? 'Welcome to the court. Bookings made in this browser are now saved to your account.' : 'Welcome to the court. Your games and scores will appear here.');
    }
}
