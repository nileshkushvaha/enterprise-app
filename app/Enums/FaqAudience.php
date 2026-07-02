<?php

declare(strict_types=1);

namespace App\Enums;

enum FaqAudience: string
{
    case Public = 'public';
    case Student = 'student';
    case Instructor = 'instructor';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Student => 'Student',
            self::Instructor => 'Instructor',
        };
    }
}
