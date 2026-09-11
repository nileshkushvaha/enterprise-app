<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Zoom-side source disposal after verified persistence (Phase 5).
 *
 * Ships OFF. When on, ZoomMeetingProvider moves a meeting's cloud
 * recordings to Zoom's recoverable trash only after the SIRI copy has
 * reached Available (stored, read back, matched). Never a permanent
 * delete, never before verification, never a condition for the SIRI
 * recording's own state.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('meeting.zoom_recording_trash_source_after_persistence', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('meeting.zoom_recording_trash_source_after_persistence');
    }
};
