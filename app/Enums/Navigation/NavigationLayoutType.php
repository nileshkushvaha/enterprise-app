<?php

namespace App\Enums\Navigation;

enum NavigationLayoutType: string
{
    case Standard  = 'standard';
    case Mega      = 'mega';
    case Tabs      = 'tabs';
    case Accordion = 'accordion';
    case Flyout    = 'flyout';

    public function label(): string
    {
        return match ($this) {
            self::Standard  => 'Standard',
            self::Mega      => 'Mega Menu',
            self::Tabs      => 'Tabs',
            self::Accordion => 'Accordion',
            self::Flyout    => 'Flyout',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Standard  => 'gray',
            self::Mega      => 'info',
            self::Tabs      => 'warning',
            self::Accordion => 'success',
            self::Flyout    => 'purple',
        };
    }
}
