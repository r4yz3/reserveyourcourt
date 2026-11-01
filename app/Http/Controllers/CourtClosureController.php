<?php

namespace App\Http\Controllers;

use App\Actions\CreateCourtClosure;
use App\Http\Requests\StoreCourtClosureRequest;
use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Court;
use App\Models\CourtClosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourtClosureController extends Controller
{
    public function store(StoreCourtClosureRequest $request, Business $business, Court $court, CreateCourtClosure $create): RedirectResponse
    {
        $create->handle($request->user(), $court, $request->validated());

        return back()->with('success', 'Closure added. These times are unavailable for staff and player bookings.');
    }

    public function destroy(Request $request, Business $business, Court $court, CourtClosure $closure): RedirectResponse
    {
        abort_unless($court->business_id === $business->id && $closure->court_id === $court->id && $request->user()->canAccessBusiness($business->id), 404);
        abort_unless($request->user()->canManageBusiness($business->id), 403);
        DB::transaction(function () use ($request, $business, $court, $closure): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            Court::query()->whereKey($court->id)->lockForUpdate()->firstOrFail();
            $closure = CourtClosure::query()->lockForUpdate()->findOrFail($closure->id);
            if ($closure->cancelled_at) {
                return;
            }
            $closure->update(['cancelled_at' => now(), 'cancelled_by' => $request->user()->id]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'court.closure_removed', 'details' => ['closure_id' => $closure->id, 'court_id' => $court->id]]);
        }, 3);

        return back()->with('success', 'Closure removed. Availability has been restored.');
    }
}
