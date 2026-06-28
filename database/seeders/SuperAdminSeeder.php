<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the super_admin role (ID 1) with every permission in the database.
 *
 * Run after shield:generate to sync all permissions:
 *   php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure super_admin role exists as ID 1
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        // 2. Sync ALL permissions to super_admin role
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);

        $this->command->info("✓ super_admin (ID: {$superAdmin->id}) synced with {$allPermissions->count()} permissions.");

        // 3. Assign super_admin role to the first user (ID 1) if not already assigned
        $user = User::find(1);
        if ($user && ! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
            $this->command->info("✓ Assigned super_admin role to: {$user->name}");
        } elseif ($user) {
            $this->command->info("✓ {$user->name} already has super_admin role.");
        }
    }
}
