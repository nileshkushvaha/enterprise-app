<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PostPermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = [
            'posts.list',
            'posts.view',
            'posts.create',
            'posts.update',
            'posts.delete',
            'posts.restore',
            'posts.publish',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
            $this->command->info('✓ Admin role granted post permissions');
        }

        $editorRole = Role::firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'web']
        );
        $editorRole->givePermissionTo([
            'posts.list',
            'posts.view',
            'posts.create',
            'posts.update',
            'posts.publish',
        ]);
        $this->command->info('✓ Editor role updated with post permissions');
        $this->command->info('✓ Post permissions seeded successfully');
    }
}
