<?php

namespace App\Enums;

enum EducationLevel: string
{
    case School = 'school';
    case Diploma = 'diploma';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';
    case Certificate = 'certificate';
    case Training = 'training';
    case Bootcamp = 'bootcamp';
    case ProfessionalCertification = 'professional_certification';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Diploma => 'Diploma',
            self::Bachelor => "Bachelor's Degree",
            self::Master => "Master's Degree",
            self::Doctorate => 'Doctorate',
            self::Certificate => 'Certificate',
            self::Training => 'Training',
            self::Bootcamp => 'Bootcamp',
            self::ProfessionalCertification => 'Professional Certification',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::School => 'gray',
            self::Diploma => 'info',
            self::Bachelor => 'primary',
            self::Master => 'success',
            self::Doctorate => 'success',
            self::Certificate => 'warning',
            self::Training => 'warning',
            self::Bootcamp => 'warning',
            self::ProfessionalCertification => 'info',
        };
    }
}
