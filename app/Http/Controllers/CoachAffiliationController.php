<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Coach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CoachAffiliationController extends Controller
{
    public function index(Request $request, Business $business): View
    {
        abort_unless($request->user()->canManageBusiness($business->id), 403);

        return view('coaches.affiliations', ['business' => $business, 'coaches' => $business->coaches()->with('user')->orderBy('coaches.id')->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        $data = $request->validate(['business_id' => ['required', 'integer']]);
        DB::transaction(function () use ($coach, $data, $request): void {
            $business = Business::query()->where('status', 'approved')->where('is_demo', false)->lockForUpdate()->findOrFail($data['business_id']);
            $coach = Coach::query()->lockForUpdate()->findOrFail($coach->id);
            if (! $coach->businesses()->whereKey($business->id)->exists()) {
                $coach->businesses()->attach($business->id, ['status' => 'pending']);
                AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'coach.affiliation_requested', 'details' => ['coach_id' => $coach->id]]);
            }
        });

        return back()->with('success', 'Affiliation request recorded. The venue owner must approve access.');
    }

    public function update(Request $request, Business $business, Coach $coach): RedirectResponse
    {
        abort_unless($request->user()->canManageBusiness($business->id), 403);
        $data = $request->validate(['status' => ['required', 'in:approved,suspended,pending']]);
        DB::transaction(function () use ($request, $business, $coach, $data): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $coach = Coach::query()->lockForUpdate()->findOrFail($coach->id);
            abort_unless($business->coaches()->whereKey($coach->id)->exists(), 404);
            $business->coaches()->updateExistingPivot($coach->id, ['status' => $data['status']]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'coach.affiliation_changed', 'details' => ['coach_id' => $coach->id, 'status' => $data['status']]]);
        });

        return back()->with('success', 'Coach affiliation updated. Approval permits lessons on the venue’s active courts. Existing lessons remain recorded.');
    }
}
