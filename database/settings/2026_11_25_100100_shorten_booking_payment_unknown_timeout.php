<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The reconciliation sweep (every five minutes) may examine a pending
 * payment attempt only once it is older than this timeout. Thirty
 * minutes was chosen when the sweep was the last resort behind a
 * webhook; with checkout completion now confirming payments directly,
 * the sweep's job is to catch the remaining stragglers quickly — a
 * captured payment must never sit unconfirmed for half an hour because
 * a webhook was lost. Lowered to five minutes for installations still
 * on the shipped default; an operator-chosen value is left alone.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update(
            'payment_gateways.booking_payment_unknown_timeout_minutes',
            fn (int $minutes): int => $minutes === 30 ? 5 : $minutes,
        );
    }

    public function down(): void
    {
        $this->migrator->update(
            'payment_gateways.booking_payment_unknown_timeout_minutes',
            fn (int $minutes): int => $minutes === 5 ? 30 : $minutes,
        );
    }
};
