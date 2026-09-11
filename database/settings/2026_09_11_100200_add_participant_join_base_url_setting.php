<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The public base URL participants are handed for joining a lesson
 * (e.g. https://meet.sirieducation.com). When set, the SIRI join
 * gateway link is generated on that host instead of APP_URL; the
 * gateway itself, its authorization and its join window are unchanged,
 * and links on the main host keep working. Ships unset.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('meeting.participant_join_base_url', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('meeting.participant_join_base_url');
    }
};
