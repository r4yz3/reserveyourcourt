<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('alias', 40)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('bio', 500)->nullable();
            $table->decimal('self_rating', 3, 2)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('country', 60)->default('Philippines');
            $table->string('address', 255)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_disk')->nullable();
            $table->boolean('show_profile')->default(true);
            $table->boolean('show_location')->default(true);
            $table->boolean('show_rating')->default(true);
            $table->boolean('show_activity')->default(true);
            $table->boolean('show_stats')->default(true);
            $table->timestamp('profile_prompt_dismissed_at')->nullable();
        });
        DB::table('users')->whereNotNull('skill_level')->update(['self_rating' => DB::raw('CAST(skill_level AS DECIMAL(3,2))')]);
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('skill_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('skill_level', 3)->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['alias', 'gender', 'bio', 'self_rating', 'contact_number', 'country', 'address', 'photo_path', 'photo_disk', 'show_profile', 'show_location', 'show_rating', 'show_activity', 'show_stats', 'profile_prompt_dismissed_at']);
        });
    }
};
