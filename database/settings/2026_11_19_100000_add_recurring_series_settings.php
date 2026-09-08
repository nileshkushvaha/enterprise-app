<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The confirmation horizon for repeating classes.
 *
 * A repeating schedule is a rule, but a booking is a reservation with a
 * price and a held slot — so the two cannot have the same reach. The
 * horizon is how far ahead the platform turns the rule into actual
 * reserved classes; everything beyond it is a planned date the student
 * can see but nobody is holding yet.
 *
 * It is NOT a limit on how many classes a student may schedule. A
 * hundred-class series is created in full as a rule; it simply confirms
 * in rolling batches, which is also what keeps generation work bounded.
 *
 * The effective horizon is min(this, booking.maximum_advance_booking_days)
 * — the bookable window is still the platform's outer limit and this
 * never widens it.
 *
 * The batch size bounds ONE generation pass per series, so a very long
 * or ongoing series is filled over several queued runs rather than in
 * one unbounded job.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.recurring_confirmation_horizon_days', 60);
        $this->migrator->add('booking.recurring_generation_batch_size', 25);
    }

    public function down(): void
    {
        $this->migrator->delete('booking.recurring_confirmation_horizon_days');
        $this->migrator->delete('booking.recurring_generation_batch_size');
    }
};
