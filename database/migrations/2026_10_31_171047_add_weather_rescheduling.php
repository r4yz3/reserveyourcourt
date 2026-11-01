<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->unsignedInteger('reschedule_version')->default(0));
        Schema::create('booking_reschedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_court_id')->constrained('courts')->restrictOnDelete();
            $table->foreignId('to_court_id')->constrained('courts')->restrictOnDelete();
            $table->dateTime('from_starts_at');
            $table->dateTime('from_ends_at');
            $table->dateTime('to_starts_at');
            $table->dateTime('to_ends_at');
            $table->string('reason');
            $table->text('staff_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('court_closure_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->timestamps();
            $table->index(['booking_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reschedules');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('reschedule_version'));
    }
};
