<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CoachAccountController extends Controller
{
    public function create(): View
    {
        return view('coaches.register');
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create($data);
            Coach::query()->create(['user_id' => $user->id]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('coach.portal')->with('success', 'Coach account created. Complete your profile while the platform team reviews your application.');
    }

    public function apply(Request $request): RedirectResponse
    {
        Coach::query()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->route('coach.portal');
    }
}
