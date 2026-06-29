<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Timestamp of when the lock was created (null = not locked or legacy lock)
            $table->timestamp('locked_at')->nullable()->after('locked_until');

            // Machine-readable reason: 'failed_attempts' | 'manual_admin' | 'manual_self'
            // Null = not locked or pre-migration legacy lock
            // Prepared for future: 'suspicious_activity', 'ip_violation', 'device_violation'
            $table->string('lock_reason', 50)->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['locked_at', 'lock_reason']);
        });
    }
};
