<?php

namespace App\Enums\Navigation;

enum NavigationLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
    case Mobile = 'mobile';
    case Sidebar = 'sidebar';
    case UserMenu = 'user_menu';
    case AdminMenu = 'admin_menu';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Header',
            self::Footer => 'Footer',
            self::Mobile => 'Mobile',
            self::Sidebar => 'Sidebar',
            self::UserMenu => 'User Menu',
            self::AdminMenu => 'Admin Menu',
            self::Custom => 'Custom',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Header => 'info',
            self::Footer => 'gray',
            self::Mobile => 'warning',
            self::Sidebar => 'primary',
            self::UserMenu => 'success',
            self::AdminMenu => 'danger',
            self::Custom => 'purple',
        };
    }
}
