<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('skill_level', 3)->nullable();
            $table->string('home_city', 120)->nullable();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['player_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['player_id', 'starts_at']);
            $table->dropConstrainedForeignId('player_id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['skill_level', 'home_city']);
        });
    }
};
