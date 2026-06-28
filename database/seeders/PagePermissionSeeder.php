<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PagePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'pages.list',
            'pages.view',
            'pages.create',
            'pages.update',
            'pages.delete',
            'pages.publish',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
            $this->command->info('✓ Admin role granted page permissions');
        }

        $editorRole = Role::firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'web']
        );
        $editorRole->givePermissionTo([
            'pages.list',
            'pages.view',
            'pages.create',
            'pages.update',
            'pages.publish',
        ]);
        $this->command->info('✓ Editor role created with page permissions');

        $this->command->info('✓ Page permissions seeded successfully');
    }
}
