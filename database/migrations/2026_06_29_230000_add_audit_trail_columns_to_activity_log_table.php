<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds actor-type awareness and request-context columns to the existing
 * activity_log table. No data is dropped; all existing rows are backfilled.
 *
 * Backfill strategy:
 *  - causer_id IS NOT NULL → actor_type = 'user'
 *  - causer_id IS NULL AND log_name = 'contact' → actor_type = 'guest'
 *  - causer_id IS NULL otherwise → actor_type = 'system'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->string('actor_type', 20)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('route', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('session_id', 100)->nullable();

            $table->index('actor_type', 'activity_log_actor_type_index');
            $table->index('guest_email', 'activity_log_guest_email_index');
        });

        // Backfill actor_type for all existing records
        DB::statement("
            UPDATE activity_log
            SET actor_type = CASE
                WHEN causer_id IS NOT NULL THEN 'user'
                WHEN log_name = 'contact'  THEN 'guest'
                ELSE 'system'
            END
            WHERE actor_type IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('activity_log_actor_type_index');
            $table->dropIndex('activity_log_guest_email_index');
            $table->dropColumn([
                'actor_type',
                'guest_name',
                'guest_email',
                'guest_phone',
                'ip_address',
                'user_agent',
                'route',
                'method',
                'session_id',
            ]);
        });
    }
};
