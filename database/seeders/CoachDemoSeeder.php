<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\FeeSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoachDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Coach demo accounts are local-only.');
        }
        $business = Business::query()->where('is_demo', true)->firstOrFail();
        $user = User::query()->firstOrCreate(['email' => 'coach@court.test'], ['name' => 'Demo coach', 'password' => 'CourtDesk-Demo-Only-2026!']);
        $coach = Coach::query()->firstOrCreate(['user_id' => $user->id], ['status' => 'approved', 'bio' => 'Local demonstration profile for private pickleball lessons. Sample data only.', 'specialties' => 'Beginner fundamentals, footwork', 'hourly_rate_centavos' => 60000, 'is_accepting' => true]);
        if (! $coach->businesses()->whereKey($business->id)->exists()) {
            $coach->businesses()->attach($business->id, ['status' => 'approved']);
        }
        for ($day = 0; $day < 7; $day++) {
            $coach->availability()->firstOrCreate(['weekday' => $day], ['start_minute' => 540, 'end_minute' => 1020]);
        }
        DB::transaction(function () use ($business, $coach): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            Coach::query()->whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $court = $business->courts()->where('is_active', true)->orderBy('id')->lockForUpdate()->first();
            $key = 'ea0b9108-59b5-49a4-8a84-28a344fe71ad';
            if (! $court || $court->closes_at - $court->opens_at < 1 || Booking::query()->where('request_key', $key)->exists()) {
                return;
            }
            $start = CarbonImmutable::now('Asia/Manila')->subDays(7)->startOfDay()->addHours($court->opens_at)->utc();
            $end = $start->addHour();
            if ($court->bookings()->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                return;
            }
            $fee = FeeSetting::query()->latest('id')->first();
            $total = $court->hourly_rate_centavos + $coach->hourly_rate_centavos;
            $booking = Booking::query()->create(['reference' => (string) Str::uuid(), 'request_key' => $key, 'business_id' => $business->id, 'court_id' => $court->id, 'coach_id' => $coach->id, 'created_by' => $business->users()->first()?->id, 'customer_name' => 'Demo student · Sample data', 'customer_contact' => 'demo-student@example.test', 'starts_at' => $start, 'ends_at' => $end, 'source' => 'online', 'status' => 'completed', 'payment_method' => 'cash', 'total_centavos' => $total, 'paid_centavos' => $total, 'coaching_centavos' => $coach->hourly_rate_centavos, 'commission_centavos' => $fee?->amount_centavos ?? 0, 'fee_setting_id' => $fee?->id, 'lesson_attendance' => 'attended', 'coach_notes' => 'Demo progress note — sample data only. Practised ready position and controlled dinks. Next session: footwork and recovery.', 'notes' => 'Local demo lesson only. No real payment or customer.']);
            for ($slot = $start; $slot->lt($end); $slot = $slot->addMinutes(30)) {
                DB::table('inventory_claims')->insert(['court_id' => $court->id, 'booking_id' => $booking->id, 'starts_at' => $slot]);
            }
        });
    }
}
