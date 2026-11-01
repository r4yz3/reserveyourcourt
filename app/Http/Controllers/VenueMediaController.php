<?php

namespace App\Http\Controllers;

use App\Actions\OptimizeImage;
use App\Models\AuditEvent;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VenueMediaController extends Controller
{
    public function store(Request $request, Business $business, OptimizeImage $images): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000']]);
        $image = $images->encode($request->file('photo'), 1920, 80, 'photo');
        $disk = config('filesystems.media_disk');
        $path = 'venue-photos/'.$business->id.'/'.Str::uuid().'.webp';
        if (! Storage::disk($disk)->put($path, $image['bytes'])) {
            throw new \RuntimeException('Venue photo storage failed.');
        }
        try {
            $previous = DB::transaction(function () use ($request, $business, $path, $disk, $image): array {
                $business = Business::query()->lockForUpdate()->findOrFail($business->id);
                $previous = [$business->photo_disk, $business->photo_path];
                $business->update(['photo_path' => $path, 'photo_disk' => $disk, 'photo_width' => $image['width'], 'photo_height' => $image['height']]);
                AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'venue.photo_updated', 'details' => ['width' => $image['width'], 'height' => $image['height']]]);

                return $previous;
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        $this->removePrevious($previous);

        return back()->with('success', 'Venue photo optimized and saved as WebP.');
    }

    public function show(Request $request, Business $business): StreamedResponse
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);
        abort_unless($business->photo_path && $business->photo_disk, 404);

        return Storage::disk($business->photo_disk)->response($business->photo_path, 'venue.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $previous = DB::transaction(function () use ($request, $business): array {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $previous = [$business->photo_disk, $business->photo_path];
            $business->update(['photo_path' => null, 'photo_disk' => null, 'photo_width' => null, 'photo_height' => null]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $business->id, 'action' => 'venue.photo_removed', 'details' => []]);

            return $previous;
        }, 3);
        $this->removePrevious($previous);

        return back()->with('success', 'Venue photo removed.');
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);
        abort_unless($request->user()->canManageBusiness($business->id), 403);
    }

    /** @param array{0:?string,1:?string} $previous */
    private function removePrevious(array $previous): void
    {
        if ($previous[0] && $previous[1]) {
            try {
                Storage::disk($previous[0])->delete($previous[1]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
