<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<string> */
    public array $selectedPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store permissions for afterCreate - they're not in Spatie Role's fillable
        $this->selectedPermissions = $data['selected_permissions'] ?? [];

        // Only pass Spatie-fillable fields
        return Arr::only($data, ['name', 'guard_name']);
    }

    protected function afterCreate(): void
    {
        /** @var Role $role */
        $role = $this->record;

        // Set extra columns directly (bypasses fillable)
        $data = $this->data;
        $role->description = $data['description'] ?? null;
        $role->status = $data['status'] ?? 'active';
        $role->remarks = $data['remarks'] ?? null;
        $role->saveQuietly();

        // Sync permissions
        $role->syncPermissions($this->selectedPermissions);

        // Activity log
        activity('roles')
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->event('created')
            ->withProperties([
                'permissions_count' => count($this->selectedPermissions),
            ])
            ->log('Role created');

        Notification::make()
            ->title('Role created')
            ->body("Role \"{$role->name}\" was created with ".count($this->selectedPermissions).' permissions.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
