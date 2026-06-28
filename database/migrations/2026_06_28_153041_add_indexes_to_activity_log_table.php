<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            // Speeds up the date-range filter on the activity log list page.
            $table->index('created_at', 'activity_log_created_at_index');

            // batch_uuid groups related activity records from a single operation
            // (e.g. all pages published in one PublishScheduledContent run).
            $table->uuid('batch_uuid')->nullable()->after('id');
            $table->index('batch_uuid', 'activity_log_batch_uuid_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('activity_log_created_at_index');
            $table->dropIndex('activity_log_batch_uuid_index');
            $table->dropColumn('batch_uuid');
        });
    }
};
