<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BookingSettings extends Settings
{
    public int $demo_duration_minutes;

    public int $reservation_expiry_minutes;

    public int $cancellation_window_hours;

    public int $reschedule_limit;

    public int $no_show_grace_minutes;

    /**
     * @deprecated Not read anywhere. The lesson lifecycle uses
     *             LessonSettings::$auto_complete_grace_minutes, which the
     *             Platform Foundation page now edits. Kept only so existing
     *             stored settings rows keep loading.
     */
    public int $auto_completion_delay_minutes;

    /** Active bookings a teacher may hold per day. null = unlimited. */
    public ?int $max_daily_bookings_per_teacher;

    // Bookable window — the only fields BookingWindowRule reads for
    // lead-time/advance-window enforcement. Named to match the original
    // settings spec; do not add a second pair of fields for this concept.
    public int $minimum_booking_notice_minutes;

    /**
     * Lead time for FREE demo bookings only — shorter than the paid
     * notice so a student can try an instructor who is free right now.
     */
    public int $demo_minimum_booking_notice_minutes;

    public int $maximum_advance_booking_days;

    /**
     * How far ahead a repeating series' classes are actually created and
     * reserved. Dates beyond it are shown to the student as planned and
     * are confirmed automatically as they come inside the horizon
     * (booking:generate-series). Never widens the bookable window — the
     * effective horizon is min(this, $maximum_advance_booking_days).
     */
    public int $recurring_confirmation_horizon_days;

    /** Occurrences one generation pass may create per series. */
    public int $recurring_generation_batch_size;

    /**
     * Whether this deployment may accept repeating schedules that reach
     * PAST the confirmation horizon — ongoing ones, and long finite
     * ones. Off until the scheduler, the queue worker and notification
     * delivery have been verified on the target environment; see the
     * deployment checklist in docs/booking.md.
     *
     * Not a cap: on means no limit, off means such a schedule is
     * refused with an explanation rather than quietly shortened.
     */
    public bool $recurring_future_generation_enabled;

    /**
     * Whether this deployment may confirm a repeating schedule's future
     * classes from the student's own balance while they are away.
     *
     * The platform CAPABILITY. The student's PERMISSION is recorded
     * separately, per schedule, on booking_series.auto_settle_from_wallet
     * — money moving unattended needs both, and this one can stop it for
     * everyone at once without editing anybody's record.
     */
    public bool $recurring_wallet_auto_settle_enabled;

    /** Key of the AssignmentStrategyInterface used to auto-assign teachers. */
    public string $assignment_strategy;

    // Guest spam protection: Cloudflare Turnstile (honeypot + rate
    // limiting stay active regardless). Authenticated flows are exempt.
    public bool $captcha_enabled;

    public ?string $turnstile_site_key;

    public ?string $turnstile_secret_key;

    /** Key of the PaymentProviderInterface handling booking payments. */
    public string $payment_provider;

    /** Minutes a paid booking holds its slot awaiting payment. */
    public int $payment_reservation_minutes;

    // Notification channels (resolved by NotificationChannelResolver).
    // WhatsApp/SMS are future gateways — stub channels log until wired.
    public bool $channel_email_enabled;

    public bool $channel_whatsapp_enabled;

    public bool $channel_sms_enabled;

    public static function group(): string
    {
        return 'booking';
    }
}
