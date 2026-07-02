<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the state_id foreign key constraint on user_profiles, once both
 * user_profiles and states are guaranteed to exist. Split out from
 * create_user_profiles_table.php because that migration is dated before
 * create_states_table.php — states didn't exist yet at that point in
 * migration history, so state_id starts as a plain unconstrained column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->foreignKeyExists()) {
            return;
        }

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropForeign(['state_id']);
        });
    }

    private function foreignKeyExists(): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'user_profiles')
            ->where('CONSTRAINT_NAME', 'user_profiles_state_id_foreign')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
