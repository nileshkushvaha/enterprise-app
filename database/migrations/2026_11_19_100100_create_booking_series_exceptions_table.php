<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-date deviations from a series' rule.
 *
 * A skip is not the absence of a booking — the absence of a booking is
 * ambiguous (not generated yet? failed? cancelled?). It is a recorded
 * decision, and it has to be durable for two reasons: future generation
 * must not recreate the date, and a counted series ("12 classes") must
 * know the date does not consume one of the twelve.
 *
 * The same table records the other two deviations a student may make on
 * one date: a class MOVED to a different time, and (written by the
 * platform, not the student) a date that has become unbookable since the
 * series was created. Keeping all three here means every reader asks one
 * question — "does this date deviate from the rule?" — instead of three.
 *
 * `local_date` is in the SERIES' timezone, matching the rule, so a
 * deviation always refers to the same calendar day the student saw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_series_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('booking_series_id');
            $table->date('local_date');
            $table->string('action', 20)->default('skipped');
            /**
             * Set only for a MOVED occurrence: the class still happens on
             * this date, at this local time instead of the rule's. Kept
             * as a per-date deviation rather than by splitting the series
             * so the schedule stays one rule the student can still edit,
             * extend and reason about.
             */
            $table->time('local_time')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('booking_series_id')->references('id')->on('booking_series')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // One decision per date: re-skipping is idempotent rather
            // than accumulating duplicate rows that would each have to
            // be de-duplicated by every reader.
            $table->unique(['booking_series_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_series_exceptions');
    }
};
