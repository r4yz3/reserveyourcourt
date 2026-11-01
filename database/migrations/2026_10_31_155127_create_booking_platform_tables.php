<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false);
        });
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('address')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('business_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->unique(['business_id', 'user_id']);
        });
        Schema::create('courts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('Indoor');
            $table->unsignedInteger('hourly_rate_centavos');
            $table->unsignedTinyInteger('opens_at')->default(6);
            $table->unsignedTinyInteger('closes_at')->default(22);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->unique(['id', 'business_id']);
        });
        Schema::create('fee_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('amount_centavos');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('court_id');
            $table->foreign(['court_id', 'business_id'])->references(['id', 'business_id'])->on('courts')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_contact');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('source');
            $table->string('status');
            $table->string('payment_method');
            $table->unsignedInteger('total_centavos');
            $table->unsignedInteger('paid_centavos')->default(0);
            $table->unsignedInteger('commission_centavos');
            $table->foreignId('fee_setting_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->dateTime('hold_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'starts_at']);
            $table->unique(['id', 'court_id']);
        });
        Schema::create('inventory_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id');
            $table->foreign(['booking_id', 'court_id'])->references(['id', 'court_id'])->on('bookings')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->unique(['court_id', 'starts_at']);
        });
        Schema::create('payment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('reference');
            $table->string('status')->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action');
            $table->json('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'payment_proofs', 'inventory_claims', 'bookings', 'fee_settings', 'courts', 'business_user', 'businesses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
    }
};
