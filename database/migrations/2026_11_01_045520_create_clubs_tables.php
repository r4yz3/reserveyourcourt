<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('country', 60)->default('Philippines');
            $table->string('city', 120)->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('media_disk')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('auto_approve')->default(true);
            $table->boolean('members_can_post')->default(false);
            $table->string('invite_token', 64)->unique();
            $table->timestamps();
            $table->index(['is_public', 'name']);
        });
        Schema::create('club_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
        Schema::create('club_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->index(['club_id', 'is_pinned', 'created_at']);
        });
        Schema::table('open_plays', function (Blueprint $table): void {
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_plays', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('club_id');
        });
        Schema::dropIfExists('club_posts');
        Schema::dropIfExists('club_members');
        Schema::dropIfExists('clubs');
    }
};
