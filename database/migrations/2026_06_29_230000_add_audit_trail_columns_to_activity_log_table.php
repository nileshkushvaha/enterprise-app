<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration. The actor_type, guest_name, guest_email, guest_phone,
 * ip_address, user_agent, route, method, and session_id columns this
 * migration used to add now live directly in create_activity_log_table
 * (squashed there since they're pure schema). This file survives solely to
 * backfill actor_type on rows that existed before that column did —
 * deleting it would violate the "never remove backfill migrations" rule,
 * and it's a no-op (0 rows) on any fresh install.
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

    /**
     * Not reversible — a backfill has no well-defined inverse (we can't
     * distinguish rows that were NULL before this ran from rows that
     * legitimately have their backfilled value).
     */
    public function down(): void {}
};
