<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds the default frontend user role (student / role ID 2).
 * This role is automatically assigned to new self-registered users.
 */
class StudentRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'student', 'guard_name' => 'web']
        );

        $this->command->info("✓ Role '{$role->name}' ready (ID: {$role->id})");
    }
}
