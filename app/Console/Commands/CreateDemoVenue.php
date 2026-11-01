<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateDemoVenue extends Command
{
    protected $signature = 'app:demo {--password= : Optional local demo password}';

    protected $description = 'Create explicitly labeled local demo accounts and sample courts';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Demo data is restricted to the local environment.');

            return self::FAILURE;
        }
        if (User::query()->where('email', 'admin@court.test')->exists()) {
            $this->error('Demo already exists. Existing credentials were not changed.');

            return self::FAILURE;
        }
        $password = $this->option('password') ?: Str::password(18);
        DB::transaction(function () use ($password): void {
            $admin = new User(['name' => 'Demo platform admin', 'email' => 'admin@court.test', 'password' => Hash::make($password)]);
            $admin->is_super_admin = true;
            $admin->save();
            $owner = User::query()->create(['name' => 'Demo venue owner', 'email' => 'owner@court.test', 'password' => Hash::make($password)]);
            $business = Business::query()->create(['name' => 'Demo · Quezon City Pickleball', 'city' => 'Quezon City', 'address' => 'Sample venue — replace before launch', 'is_demo' => true, 'payment_instructions' => 'Demo only. No live payment account is configured. Do not send money.']);
            $business->users()->attach($owner, ['role' => 'owner']);
            foreach (['Court 1', 'Court 2', 'Court 3'] as $name) {
                $business->courts()->create(['name' => $name, 'type' => 'Indoor', 'hourly_rate_centavos' => 40000, 'opens_at' => 6, 'closes_at' => 22]);
            }
        });
        $this->info('LOCAL DEMO ONLY — sample venue and courts created. Configure the fee before creating fully paid reservations.');
        $this->line('Admin: admin@court.test');
        $this->line('Owner: owner@court.test');
        $this->line('Password for both accounts: '.$password);
        $this->warn('The booking fee is intentionally unset. Configure it using the admin account.');

        return self::SUCCESS;
    }
}
