<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoachAccountController extends Controller
{
    /**
     * Coaches sign up with the same account as everyone else, then continue to coach setup.
     */
    public function create(): RedirectResponse
    {
        redirect()->setIntendedUrl(route('coach.portal'));

        return redirect()->route('register');
    }

    public function apply(Request $request): RedirectResponse
    {
        Coach::query()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->route('coach.portal');
    }
}
