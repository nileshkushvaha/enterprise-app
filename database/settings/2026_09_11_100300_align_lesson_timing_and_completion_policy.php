<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The agreed lesson timing and completion policy (see
 * docs/deployment/lesson-completion-policy.md):
 *
 *  - joining opens 10 minutes before the scheduled start and closes 5
 *    minutes after the scheduled end (was 15 / 15);
 *  - an ended lesson is completed 15 minutes after its scheduled end
 *    (was 30), processed every 5 minutes, so completion lands 15–20
 *    minutes after the end;
 *  - attendance evidence is sealed at the same 15-minute mark, and the
 *    provider attendance pull starts 5 minutes after the end so a pull
 *    has happened before the seal.
 *
 * Forward-only in effect: down() restores the previous shipped values
 * so a rollback is a plain settings revert. Deliberately NOT touched:
 * lessons.automated_finalization_enabled — evidence-based finalization
 * is activated separately and audited (lessons:evidence-finalization).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('meeting.meeting_link_visible_before_minutes', fn (): int => 10);
        $this->migrator->update('meeting.meeting_link_visible_after_minutes', fn (): int => 5);
        $this->migrator->update('lessons.auto_complete_grace_minutes', fn (): int => 15);
        $this->migrator->update('lessons.attendance_finalize_delay_minutes', fn (): int => 15);
        $this->migrator->update('meeting.attendance_sync_delay_minutes', fn (): int => 5);
    }

    public function down(): void
    {
        $this->migrator->update('meeting.meeting_link_visible_before_minutes', fn (): int => 15);
        $this->migrator->update('meeting.meeting_link_visible_after_minutes', fn (): int => 15);
        $this->migrator->update('lessons.auto_complete_grace_minutes', fn (): int => 30);
        $this->migrator->update('lessons.attendance_finalize_delay_minutes', fn (): int => 30);
        $this->migrator->update('meeting.attendance_sync_delay_minutes', fn (): int => 15);
    }
};
