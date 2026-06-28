<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: update_activity_log_subject_id_to_uuid
//         add_indexes_to_activity_log_table (batch_uuid column + created_at/batch_uuid indexes)
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
            $table->timestamps();

            $table->index('created_at', 'activity_log_created_at_index');
            $table->index('batch_uuid', 'activity_log_batch_uuid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
