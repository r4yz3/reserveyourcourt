<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->text('bio')->nullable();
            $table->string('specialties')->nullable();
            $table->unsignedInteger('hourly_rate_centavos')->default(0);
            $table->boolean('is_accepting')->default(false);
            $table->timestamps();
        });
        Schema::create('business_coach', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('coach_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['business_id', 'coach_id']);
        });
        Schema::create('coach_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->timestamps();
            $table->unique(['coach_id', 'weekday']);
        });
        Schema::create('coach_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason');
            $table->timestamps();
            $table->index(['coach_id', 'starts_at']);
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('coach_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('coaching_centavos')->default(0);
            $table->string('lesson_attendance')->nullable();
            $table->text('coach_notes')->nullable();
            $table->index(['coach_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['coach_id', 'starts_at']);
            $table->dropConstrainedForeignId('coach_id');
            $table->dropColumn(['coaching_centavos', 'lesson_attendance', 'coach_notes']);
        });
        Schema::dropIfExists('coach_blocks');
        Schema::dropIfExists('coach_availabilities');
        Schema::dropIfExists('business_coach');
        Schema::dropIfExists('coaches');
    }
};
