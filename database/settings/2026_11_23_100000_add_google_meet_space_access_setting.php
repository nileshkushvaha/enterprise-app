<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Who may join a lesson's Meet space and whether it can start without
 * the platform host — an administrator decision, so it lives in
 * settings rather than code. See App\Booking\Enums\GoogleMeetSpaceAccess.
 * Ships as "trusted" (Meet's default): the host starts every class and
 * admits participants, which keeps the whole lesson on the recording.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('meeting.google_meet_space_access', 'trusted');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('meeting.google_meet_space_access');
    }
};
