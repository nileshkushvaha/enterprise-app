<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * Who may join a lesson's Google Meet space, and whether a class can
 * start without the platform host. Mirrors Meet's own
 * `SpaceConfig.accessType` values; chosen by administrators on
 * Settings → Meetings (MeetingSettings::google_meet_space_access).
 *
 * Meet records only while the host is present, whichever value is
 * chosen — so Trusted is the setting that guarantees a whole-lesson
 * recording, and Open is the setting that lets a class run without
 * staff present at the cost of recording only from the host's arrival.
 */
enum GoogleMeetSpaceAccess: string
{
    /** Outside participants wait in the lobby until the host has joined and admits them. Meet's default. */
    case Trusted = 'trusted';

    /** Anyone with the link joins without knocking; the meeting can start without the host. */
    case Open = 'open';

    /** Only invited members join; everyone else is refused. */
    case Restricted = 'restricted';

    /** The value Google's API expects. */
    public function apiValue(): string
    {
        return strtoupper($this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trusted => 'Host admits participants (default)',
            self::Open => 'Anyone with the link can join, even before the host',
            self::Restricted => 'Invited members only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trusted => 'Students and instructors wait in the lobby until the platform host joins and admits them. Only the host can start a class, and the whole lesson is recorded.',
            self::Open => 'The class can start without staff present. Recording still begins only when the platform host joins.',
            self::Restricted => 'Only Google accounts added to the space may join — not usable with external instructors and students.',
        };
    }

    /** @return array<string, string> value => label, for the settings form */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Tolerant parse for a stored setting; anything unknown is the safe default. */
    public static function fromSetting(?string $value): self
    {
        return self::tryFrom(strtolower((string) $value)) ?? self::Trusted;
    }
}
