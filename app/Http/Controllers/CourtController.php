<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourtRequest;
use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Court;
use App\Models\CourtClosure;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourtController extends Controller
{
    public function edit(Request $request, Business $business, Court $court): View
    {
        $this->authorizeOwner($request, $business, $court);
        $closures = CourtClosure::query()->where('court_id', $court->id)->whereNull('cancelled_at')->where('ends_at', '>', now())->orderBy('starts_at')->paginate(15);

        return view('courts.edit', compact('business', 'court', 'closures'));
    }

    public function update(UpdateCourtRequest $request, Business $business, Court $court): RedirectResponse
    {
        $this->authorizeOwner($request, $business, $court);
        $request->validate(['name' => [Rule::unique('courts')->where('business_id', $business->id)->ignore($court->id)]]);
        $data = $request->validated();
        $data['hourly_rate_centavos'] = Money::centavos((string) $data['hourly_rate']);
        unset($data['hourly_rate']);
        DB::transaction(function () use ($request, $business, $court, $data): void {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $court = Court::query()->lockForUpdate()->findOrFail($court->id);
            $upcoming = $court->bookings()->where('ends_at', '>', now())->where(fn ($q) => $q->whereIn('status', ['confirmed', 'completed', 'no_show'])->orWhere(fn ($p) => $p->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '>', now())))->get();
            if (! $data['is_active'] && $upcoming->isNotEmpty()) {
                throw ValidationException::withMessages(['is_active' => 'This court has upcoming reservations or payment holds. Resolve them before deactivating it.']);
            }
            foreach ($upcoming as $booking) {
                $start = $booking->starts_at->setTimezone('Asia/Manila');
                $end = $booking->ends_at->setTimezone('Asia/Manila');
                if ($start->hour < (int) $data['opens_at'] || $end->gt($start->startOfDay()->addHours((int) $data['closes_at']))) {
                    throw ValidationException::withMessages(['opens_at' => 'These opening hours would exclude an existing reservation. Resolve affected bookings before changing the hours.']);
                }
            }
            $before = $court->only(['name', 'type', 'hourly_rate_centavos', 'opens_at', 'closes_at', 'is_active']);
            $court->update($data);
            if (! $court->is_active && $business->status === 'approved' && ! $business->courts()->where('is_active', true)->exists()) {
                $business->update(['status' => 'draft']);
            }
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'court.updated', 'details' => ['court_id' => $court->id, 'previous' => $before, 'current' => $court->only(array_keys($before))]]);
        }, 3);

        return back()->with('success', 'Court updated. Existing booking prices and platform fees are unchanged.');
    }

    private function authorizeOwner(Request $request, Business $business, Court $court): void
    {
        abort_unless($court->business_id === $business->id && $request->user()->canAccessBusiness($business->id), 404);
        abort_unless($request->user()->canManageBusiness($business->id), 403);
    }
}
