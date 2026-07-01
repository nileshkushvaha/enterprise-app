<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: update_activity_log_subject_id_to_uuid
//         add_indexes_to_activity_log_table (batch_uuid column + created_at/batch_uuid indexes)
//         add_audit_trail_columns_to_activity_log_table (actor_type, guest_*, ip_address,
//         user_agent, route, method, session_id + actor_type/guest_email indexes — the
//         DB::statement() backfill in that migration is data-only and stays in its own file)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->nullable();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('route', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->timestamps();

            $table->index('created_at', 'activity_log_created_at_index');
            $table->index('batch_uuid', 'activity_log_batch_uuid_index');
            $table->index('actor_type', 'activity_log_actor_type_index');
            $table->index('guest_email', 'activity_log_guest_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
