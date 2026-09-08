<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the student was told about a class that could not be booked.
 *
 * The exception row itself is written with updateOrCreate, so a retried
 * or repeated generation pass legitimately touches it again — which
 * makes "the row exists" useless as a has-been-notified signal. This
 * column is that signal, and it lives in the database rather than in a
 * queue guard because it has to survive a worker restart, a redelivered
 * job, and a second pass days later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_series_exceptions', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('booking_series_exceptions', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });
    }
};
