<?php

namespace App\Http\Controllers;

use App\Actions\AvailableCourtSlots;
use App\Models\Business;
use App\Models\FeeSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicVenueController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $venues = Business::query()->where('status', 'approved')->where('is_demo', false)->whereHas('courts', fn ($q) => $q->where('is_active', true))
            ->with(['courts' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('city', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        return view('public.index', compact('venues'));
    }

    public function show(Request $request, Business $business, AvailableCourtSlots $availability): View
    {
        $this->assertVisible($business);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Manila')->toDateString(), 'before_or_equal:'.now('Asia/Manila')->addDays(90)->toDateString()], 'duration' => ['nullable', 'integer', 'in:30,60,90,120,150,180,240']]);
        $date = CarbonImmutable::parse($data['date'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        $duration = (int) ($data['duration'] ?? 60);
        $courts = $business->courts()->where('is_active', true)->orderBy('name')->get();
        $slots = $availability->handle($courts, $date, $duration);

        return view('public.venue', compact('business', 'courts', 'date', 'duration', 'slots') + ['bookingEnabled' => FeeSetting::query()->exists(), 'requestKey' => (string) Str::uuid()]);
    }

    public function photo(Business $business): StreamedResponse
    {
        $this->assertVisible($business);
        abort_unless($business->photo_path && $business->photo_disk, 404);

        return Storage::disk($business->photo_disk)->response($business->photo_path, 'venue.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function assertVisible(Business $business): void
    {
        abort_unless($business->status === 'approved' && ! $business->is_demo && $business->courts()->where('is_active', true)->exists(), 404);
    }
}
