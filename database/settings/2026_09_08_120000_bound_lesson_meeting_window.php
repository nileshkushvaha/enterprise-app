<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Bounds a lesson's meeting to its own timeslot.
 *
 * Before this, the join link stayed live for an hour after a lesson
 * ended and nothing ever shut the meeting down, so a Google Meet space
 * could keep running — and, with automatic recording on, keep recording
 * — long after the class was over.
 *
 * The platform policy is now a symmetric quarter of an hour: a
 * participant may join from 15 minutes before the start until 15
 * minutes after the end, and the meeting is closed at that same
 * boundary. Both numbers stay admin-editable in
 * Admin → Settings → Meetings.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('meeting.meeting_auto_close_enabled', true);

        $this->migrator->update('meeting.meeting_link_visible_before_minutes', fn (): int => 15);
        $this->migrator->update('meeting.meeting_link_visible_after_minutes', fn (): int => 15);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('meeting.meeting_auto_close_enabled');
    }
};
