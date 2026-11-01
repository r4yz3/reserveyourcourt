<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Coach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CoachApprovalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('coaches.approvals', ['coaches' => Coach::query()->with('user')->orderBy('status')->orderBy('id')->paginate(20)]);
    }

    public function update(Request $request, Coach $coach): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin, 403);
        $data = $request->validate(['status' => ['required', 'in:approved,suspended,pending']]);
        DB::transaction(function () use ($request, $coach, $data): void {
            $coach = Coach::query()->lockForUpdate()->findOrFail($coach->id);
            $coach->update(['status' => $data['status']]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'action' => 'coach.status_changed', 'details' => ['coach_id' => $coach->id, 'status' => $data['status']]]);
        });

        return back()->with('success', 'Coach status updated. Existing lessons remain recorded.');
    }
}
