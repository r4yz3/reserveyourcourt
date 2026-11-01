<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessRequest;
use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:draft,approved,suspended']]);
        $businesses = Business::query()->with('users:id,name,email')->withCount('courts')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('city', 'like', '%'.$search.'%')))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('businesses.index', compact('businesses'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('businesses.create');
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $business = DB::transaction(function () use ($request, $data): Business {
            $owner = $data['owner_mode'] === 'new'
               ? User::query()->create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => $data['password']])
               : User::query()->where('email', $data['owner_email'])->firstOrFail();
            $business = Business::query()->create(['name' => $data['name'], 'city' => $data['city'], 'address' => $data['address'] ?? null, 'status' => 'draft']);
            $business->users()->attach($owner->id, ['role' => 'owner']);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'business.created', 'details' => ['owner_id' => $owner->id, 'owner_mode' => $data['owner_mode']]]);

            return $business;
        });

        return redirect()->route('venues.edit', $business)->with('success', 'Business created as a draft and owner assigned. No invitation email was sent.');
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin, 403);
        $data = $request->validate(['status' => ['required', 'in:draft,approved,suspended']]);
        DB::transaction(function () use ($request, $business, $data): void {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ($data['status'] === 'approved' && ($business->is_demo || blank($business->address) || blank($business->payment_instructions) || ! $business->courts()->where('is_active', true)->exists())) {
                throw ValidationException::withMessages(['status' => 'Approval requires a real venue, an address, payment instructions and at least one active court.']);
            }
            $previous = $business->status;
            $business->update(['status' => $data['status']]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'business.status_changed', 'details' => ['previous' => $previous, 'status' => $data['status']]]);
        }, 3);

        return back()->with('success', 'Business status updated. Suspension blocks new reservations and preserves existing records.');
    }
}
