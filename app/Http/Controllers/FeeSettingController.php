<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFeeRequest;
use App\Models\AuditEvent;
use App\Models\FeeSetting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('settings.fees', ['fee' => FeeSetting::query()->latest('id')->first(), 'history' => FeeSetting::query()->latest('id')->limit(15)->get()]);
    }

    public function update(UpdateFeeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $previous = FeeSetting::query()->latest('id')->first();
            $fee = FeeSetting::query()->create(['amount_centavos' => Money::centavos((string) $request->validated('amount')), 'changed_by' => $request->user()->id]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'action' => 'fee.changed', 'details' => ['previous_centavos' => $previous?->amount_centavos, 'amount_centavos' => $fee->amount_centavos, 'fee_setting_id' => $fee->id]]);
        });

        return back()->with('success', 'Fee saved for future bookings. Existing bookings keep their saved fee.');
    }
}
