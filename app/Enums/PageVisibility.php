<?php

namespace App\Enums;

enum PageVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'warning',
        };
    }
}
