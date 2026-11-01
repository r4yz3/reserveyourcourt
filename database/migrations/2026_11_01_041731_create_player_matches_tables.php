<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->date('played_on');
            $table->string('format');
            $table->string('location', 160)->nullable();
            $table->json('games');
            $table->unsignedTinyInteger('winning_side');
            $table->string('status')->default('unconfirmed');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['recorded_by', 'played_on']);
        });
        Schema::create('match_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_match_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('side');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['player_match_id', 'user_id']);
            $table->index(['user_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('player_matches');
    }
};
