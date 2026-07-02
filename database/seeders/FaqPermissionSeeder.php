<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FaqPermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faqPermissions = [
            'ViewAny:Faq',
            'View:Faq',
            'Create:Faq',
            'Update:Faq',
            'Delete:Faq',
            'DeleteAny:Faq',
            'Restore:Faq',
            'RestoreAny:Faq',
            'ForceDelete:Faq',
            'ForceDeleteAny:Faq',
            'Replicate:Faq',
            'Reorder:Faq',
        ];

        $categoryPermissions = [
            'ViewAny:FaqCategory',
            'View:FaqCategory',
            'Create:FaqCategory',
            'Update:FaqCategory',
            'Delete:FaqCategory',
            'DeleteAny:FaqCategory',
            'Restore:FaqCategory',
            'RestoreAny:FaqCategory',
            'ForceDelete:FaqCategory',
            'ForceDeleteAny:FaqCategory',
            'Replicate:FaqCategory',
            'Reorder:FaqCategory',
        ];

        $all = array_merge($faqPermissions, $categoryPermissions);

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo(['ViewAny:Faq', 'View:Faq', 'ViewAny:FaqCategory', 'View:FaqCategory']);

        $instructorRole = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $instructorRole->givePermissionTo([
            'ViewAny:Faq', 'View:Faq', 'Create:Faq', 'Update:Faq',
            'ViewAny:FaqCategory', 'View:FaqCategory', 'Create:FaqCategory', 'Update:FaqCategory',
        ]);

        $this->command->info('✓ FAQ permissions seeded successfully');
    }
}
