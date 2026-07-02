<?php

namespace App\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Internship = 'internship';
    case Freelance = 'freelance';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-Time',
            self::PartTime => 'Part-Time',
            self::Contract => 'Contract',
            self::Internship => 'Internship',
            self::Freelance => 'Freelance',
            self::Volunteer => 'Volunteer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FullTime => 'success',
            self::PartTime => 'info',
            self::Contract => 'warning',
            self::Internship => 'gray',
            self::Freelance => 'primary',
            self::Volunteer => 'gray',
        };
    }
}
