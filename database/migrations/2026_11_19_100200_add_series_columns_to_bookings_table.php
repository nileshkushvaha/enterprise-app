<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an individual class booking to the series that scheduled it.
 *
 * Both columns are nullable and nothing is backfilled: every existing
 * booking — including historical recurring ones identified only by
 * `meta.recurring_group` — keeps its exact current shape and continues
 * to be read the same way. New series bookings additionally keep
 * writing `meta.recurring_group` (set to the series id), so existing
 * readers, reports and API consumers are unaffected by the new model.
 *
 * The unique index is the load-bearing part. `series_occurrence_date`
 * is the occurrence's date in the SERIES' timezone, so
 * (series, date) names exactly one class. That makes generation
 * idempotent at the database level rather than by convention: a
 * retried job, a duplicated queue message, or two workers racing the
 * same series can each attempt the same occurrence and only one row can
 * ever exist. Cancelled occurrences keep their row, so regeneration
 * cannot resurrect a class the student called off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->uuid('booking_series_id')->nullable()->after('recurrence_frequency');
            $table->date('series_occurrence_date')->nullable()->after('booking_series_id');

            $table->foreign('booking_series_id')->references('id')->on('booking_series')->nullOnDelete();
            $table->unique(['booking_series_id', 'series_occurrence_date'], 'bookings_series_occurrence_unique');
            $table->index(['booking_series_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['booking_series_id']);
            $table->dropUnique('bookings_series_occurrence_unique');
            $table->dropIndex(['booking_series_id', 'starts_at']);
            $table->dropColumn(['booking_series_id', 'series_occurrence_date']);
        });
    }
};
