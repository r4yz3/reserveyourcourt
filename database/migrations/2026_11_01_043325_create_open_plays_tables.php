<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_plays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('privacy')->default('public');
            $table->string('invite_token', 64)->unique();
            $table->string('passkey_hash')->nullable();
            $table->string('location', 160)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->string('format')->default('doubles');
            $table->decimal('min_rating', 3, 2)->nullable();
            $table->decimal('max_rating', 3, 2)->nullable();
            $table->string('gender_rule')->default('any');
            $table->unsignedInteger('fee_centavos')->default(0);
            $table->string('payment_note', 255)->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->index(['status', 'privacy', 'starts_at']);
            $table->index(['host_id', 'starts_at']);
        });
        Schema::create('open_play_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('open_play_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('joined');
            $table->timestamps();
            $table->unique(['open_play_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_participants');
        Schema::dropIfExists('open_plays');
    }
};
