<?php

namespace App\Enums\Navigation;

enum NavigationLinkType: string
{
    case Page     = 'page';
    case Post     = 'post';
    case Category = 'category';
    case Tag      = 'tag';
    case Route    = 'route';
    case Url      = 'url';
    case External = 'external';
    case Email    = 'email';
    case Phone    = 'phone';
    case Anchor   = 'anchor';
    case Custom   = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Page     => 'Page',
            self::Post     => 'Post',
            self::Category => 'Category',
            self::Tag      => 'Tag',
            self::Route    => 'Named Route',
            self::Url      => 'URL',
            self::External => 'External URL',
            self::Email    => 'Email Address',
            self::Phone    => 'Phone Number',
            self::Anchor   => 'Anchor',
            self::Custom   => 'Custom',
        };
    }

    public function usesLinkable(): bool
    {
        return match ($this) {
            self::Page, self::Post, self::Category, self::Tag => true,
            default => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Page, self::Post => 'info',
            self::Category, self::Tag => 'warning',
            self::Route, self::Url => 'success',
            self::External => 'danger',
            self::Email, self::Phone => 'purple',
            self::Anchor, self::Custom => 'gray',
        };
    }
}
