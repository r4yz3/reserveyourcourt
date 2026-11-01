<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->change();
            $table->string('guest_key_hash', 64)->nullable()->index();
        });
        Schema::table('audit_events', fn (Blueprint $table) => $table->foreignId('user_id')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['guest_key_hash']);
            $table->dropColumn('guest_key_hash');
            $table->foreignId('created_by')->nullable(false)->change();
        });
        Schema::table('audit_events', fn (Blueprint $table) => $table->foreignId('user_id')->nullable(false)->change());
    }
};
