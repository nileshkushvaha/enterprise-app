<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (booking, host) capacity claim. A booking that will run
 * on a capacity-limited provider reserves its host at the moment the
 * booking is ACCEPTED — the same transaction that creates the booking
 * row — so two instructors can never both sell the one Zoom licence
 * for the same hour.
 *
 * `occupies_from`/`occupies_until` are UTC and WIDER than the lesson:
 * they include the early-join allowance, the late-join/closure
 * allowance and an operational buffer (MeetingHostCapacityService).
 * Overlap is tested on this interval, half-open, so back-to-back
 * lessons that merely touch do not collide.
 *
 * History is kept: a reservation is never deleted, only `released`
 * (cancellation, hold expiry, reschedule, completion) with a reason.
 * Capacity counts only `active` rows. There is no unique index on
 * booking_id because a rescheduled booking legitimately owns one
 * released and one active row; "at most one ACTIVE per booking" is
 * enforced by MeetingHostCapacityService under the host row lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_host_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_meeting_host_id')->constrained('platform_meeting_hosts')->restrictOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('provider', 32);

            $table->dateTime('lesson_starts_at');
            $table->dateTime('lesson_ends_at');
            $table->dateTime('occupies_from');
            $table->dateTime('occupies_until');

            $table->string('status', 16)->default('active');
            // Mirrors bookings.reserved_until for a pending-payment hold;
            // informational — release happens when the hold is cancelled.
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->string('release_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['platform_meeting_host_id', 'status', 'occupies_from', 'occupies_until'], 'meeting_host_reservations_capacity_index');
            $table->index(['booking_id', 'status'], 'meeting_host_reservations_booking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_host_reservations');
    }
};
