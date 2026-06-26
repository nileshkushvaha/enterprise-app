<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // ── Two-Factor Authentication ─────────────────────────
            $table->string('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // ── Account unlock token (self-service unlock email) ──
            $table->string('unlock_token', 64)->nullable()->after('locked_until');
            $table->timestamp('unlock_token_expires_at')->nullable()->after('unlock_token');

            // ── Security settings ─────────────────────────────────
            $table->boolean('login_alerts_enabled')->default(true)->after('unlock_token_expires_at');
            $table->boolean('new_device_alerts_enabled')->default(true)->after('login_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'unlock_token',
                'unlock_token_expires_at',
                'login_alerts_enabled',
                'new_device_alerts_enabled',
            ]);
        });
    }
};
