<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recurring class schedule, modelled as a SERIES that owns individual
 * class bookings rather than as a property of each booking.
 *
 * Before this, a recurring request was N independent `bookings` rows
 * sharing a `meta.recurring_group` uuid. That shape can express "these
 * twelve happened together" but nothing else: there is no rule to
 * consult, so nothing can be extended, no future date can be generated
 * later, and an ongoing schedule is impossible by construction — every
 * class has to exist up front, which is exactly why a hard cap was
 * needed.
 *
 * Storing the RULE instead makes the cap unnecessary. Bookings are
 * created rolling-forward inside the confirmation horizon, the rest of
 * the schedule is a fact about this row, and `generated_through_date`
 * is the watermark that makes generation resumable and idempotent.
 *
 * Purely additive: no existing table, column or row is changed, and
 * every historical `meta.recurring_group` series keeps working exactly
 * as it does today (with `booking_series_id` simply null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_series', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('booking_type_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('instructor_id');

            $table->string('status', 20)->default('active');

            // ── The rule ────────────────────────────────────────────────
            $table->string('frequency', 20);
            // Repeat every N days (daily) or N weeks (weekly).
            $table->unsignedSmallInteger('repeat_interval')->default(1);
            // Weekly only: Carbon day-of-week numbers (Sunday = 0). Null
            // for daily; an empty/absent list means the start date's day.
            $table->json('weekdays')->nullable();
            $table->date('start_date');
            $table->time('time_of_day');
            $table->unsignedSmallInteger('duration_minutes');

            /**
             * The series' OWN calendar — the clock "every Monday at 18:00"
             * is a statement about. Stored explicitly, never re-derived:
             * weekday membership, the every-N-weeks phase, the end date
             * and the intended wall clock across a daylight-saving change
             * are all meaningless without it, and it must not move when a
             * profile timezone is later edited.
             */
            $table->string('timezone', 64);

            /**
             * The student's display timezone at creation — provenance
             * only, mirroring `bookings.timezone`. Never used to decide
             * when a class happens.
             */
            $table->string('student_timezone', 64);

            $table->string('end_condition', 20);
            $table->date('end_date')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();

            // ── Generation state ────────────────────────────────────────
            /**
             * The last local date this series has been generated through.
             * Generation resumes strictly after it, so a retried or
             * interrupted run repeats no work; combined with the unique
             * index on (booking_series_id, series_occurrence_date) it is
             * what makes generation idempotent rather than merely
             * usually-safe.
             */
            $table->date('generated_through_date')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->unsignedInteger('generation_failures')->default(0);

            $table->json('meta')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('booking_type_id')->references('id')->on('booking_types')->restrictOnDelete();
            // Historical business records, exactly like `bookings`.
            $table->foreign('student_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('instructor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['student_id', 'status']);
            $table->index(['instructor_id', 'status']);
            // The generation sweep's access path: active series whose
            // horizon has not been filled yet.
            $table->index(['status', 'generated_through_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_series');
    }
};
