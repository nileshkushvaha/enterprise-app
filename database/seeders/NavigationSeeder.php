<?php

namespace Database\Seeders;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationMenu;
use Illuminate\Database\Seeder;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            [
                'name'        => 'Header Navigation',
                'slug'        => 'header',
                'location'    => NavigationLocation::Header->value,
                'layout_type' => NavigationLayoutType::Standard->value,
                'status'      => NavigationStatus::Published->value,
                'description' => 'Primary navigation shown in the site header.',
            ],
            [
                'name'        => 'Footer Navigation',
                'slug'        => 'footer',
                'location'    => NavigationLocation::Footer->value,
                'layout_type' => NavigationLayoutType::Standard->value,
                'status'      => NavigationStatus::Published->value,
                'description' => 'Links displayed in the site footer.',
            ],
            [
                'name'        => 'Mobile Navigation',
                'slug'        => 'mobile',
                'location'    => NavigationLocation::Mobile->value,
                'layout_type' => NavigationLayoutType::Accordion->value,
                'status'      => NavigationStatus::Published->value,
                'description' => 'Navigation optimised for mobile devices.',
            ],
            [
                'name'        => 'Sidebar Navigation',
                'slug'        => 'sidebar',
                'location'    => NavigationLocation::Sidebar->value,
                'layout_type' => NavigationLayoutType::Standard->value,
                'status'      => NavigationStatus::Draft->value,
                'description' => 'Sidebar contextual navigation.',
            ],
            [
                'name'        => 'User Menu',
                'slug'        => 'user-menu',
                'location'    => NavigationLocation::UserMenu->value,
                'layout_type' => NavigationLayoutType::Standard->value,
                'status'      => NavigationStatus::Published->value,
                'description' => 'Dropdown menu shown in the authenticated user avatar.',
            ],
            [
                'name'        => 'Admin Menu',
                'slug'        => 'admin-menu',
                'location'    => NavigationLocation::AdminMenu->value,
                'layout_type' => NavigationLayoutType::Standard->value,
                'status'      => NavigationStatus::Published->value,
                'description' => 'Administrative navigation visible to admin users.',
            ],
        ];

        foreach ($menus as $menu) {
            NavigationMenu::firstOrCreate(
                ['slug' => $menu['slug']],
                $menu,
            );
        }
    }
}
