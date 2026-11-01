<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('status')->default('draft')->index();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_disk')->nullable();
            $table->unsignedInteger('photo_width')->nullable();
            $table->unsignedInteger('photo_height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'description', 'photo_path', 'photo_disk', 'photo_width', 'photo_height']);
        });
    }
};
