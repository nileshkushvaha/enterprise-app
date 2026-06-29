<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityActorType: string
{
    case User = 'user';
    case Guest = 'guest';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Guest => 'Guest',
            self::System => 'System',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::User => 'info',
            self::Guest => 'warning',
            self::System => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::User => 'heroicon-o-user',
            self::Guest => 'heroicon-o-globe-alt',
            self::System => 'heroicon-o-cog-6-tooth',
        };
    }
}
