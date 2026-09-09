<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The platform switch for confirming future classes from a student's
 * balance without them present.
 *
 * Separate from the per-schedule consent on purpose: this is the
 * capability, that is the permission. Money moving while nobody is
 * watching needs both, and needs to be stoppable for everyone at once
 * without editing a single student's record.
 *
 * Off until the deployment checklist in docs/booking.md has been walked
 * — the same discipline as recurring_future_generation_enabled, and for
 * the same reason: the behaviour depends on a queue worker actually
 * running here.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.recurring_wallet_auto_settle_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('booking.recurring_wallet_auto_settle_enabled');
    }
};
