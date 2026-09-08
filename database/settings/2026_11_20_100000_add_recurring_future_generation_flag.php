<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The release gate for schedules that cannot be fully reserved at
 * booking time.
 *
 * A repeating schedule that reaches past the confirmation horizon — an
 * ongoing one, or a long finite one — is only a real promise if the
 * background pass that fills it in is actually running on this
 * deployment. Tested generation code does not establish that: the cron
 * may not be installed, the queue worker may not be supervised, and a
 * student who booked "40 classes" would then quietly receive eight.
 *
 * So this starts OFF, and is enforced server-side rather than by hiding
 * a control. It stays off until the deployment checklist in
 * docs/booking.md has been walked on the target environment.
 *
 * It is NOT a class-count cap. With it on there is no limit at all;
 * with it off a schedule that needs future generation is REFUSED with
 * an explanation, never silently shortened.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.recurring_future_generation_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('booking.recurring_future_generation_enabled');
    }
};
