<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourtRequest;
use App\Http\Requests\UpdateVenueRequest;
use App\Models\AuditEvent;
use App\Models\Business;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function edit(Request $request, Business $business): View
    {
        $this->authorizeOwner($request, $business);

        return view('venues.edit', ['business' => $business, 'courts' => $business->courts()->orderBy('name')->get()]);
    }

    public function update(UpdateVenueRequest $request, Business $business): RedirectResponse
    {
        DB::transaction(function () use ($request, $business): void {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $business->fill($request->validated());
            if ($business->status === 'approved' && (blank($business->address) || blank($business->payment_instructions))) {
                $business->status = 'draft';
            }
            $business->save();
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'venue.updated', 'details' => ['status' => $business->status]]);
        }, 3);

        return back()->with('success', 'Venue details and payment instructions saved.');
    }

    public function storeCourt(StoreCourtRequest $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $data = $request->validated();
        $data['hourly_rate_centavos'] = Money::centavos((string) $data['hourly_rate']);
        unset($data['hourly_rate']);
        $request->validate(['name' => [Rule::unique('courts')->where('business_id', $business->id)]]);
        DB::transaction(function () use ($request, $business, $data): void {
            $court = $business->courts()->create($data);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'court.created', 'details' => ['court_id' => $court->id]]);
        });

        return back()->with('success', 'Court added to the schedule.');
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);
        abort_unless($request->user()->canManageBusiness($business->id), 403);
    }
}
