<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<string> Permission names selected in the matrix */
    public array $selectedPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Pre-populate selectedPermissions so Alpine matrix reads correct state
        $this->selectedPermissions = $this->record->permissions->pluck('name')->toArray();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // selectedPermissions is updated live by Alpine via $wire.selectedPermissions
        // Only pass Spatie-fillable fields to the model save
        return Arr::only($data, ['name', 'guard_name']);
    }

    protected function afterSave(): void
    {
        /** @var Role $role */
        $role = $this->record;

        $data = $this->data;

        // Capture old permissions for activity log diff
        $oldPermissions = $role->permissions->pluck('name')->toArray();

        // Set extra columns directly
        $role->description = $data['description'] ?? null;
        $role->status      = $data['status'] ?? 'active';
        $role->remarks     = $data['remarks'] ?? null;
        $role->saveQuietly();

        // Sync permissions from the matrix state
        $role->syncPermissions($this->selectedPermissions);

        // Activity log with diff
        $added   = array_diff($this->selectedPermissions, $oldPermissions);
        $removed = array_diff($oldPermissions, $this->selectedPermissions);

        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'permissions_added'   => array_values($added),
                'permissions_removed' => array_values($removed),
                'total_permissions'   => count($this->selectedPermissions),
            ])
            ->log('Role updated');

        Notification::make()
            ->title('Role saved')
            ->body("Role \"{$role->name}\" updated with " . count($this->selectedPermissions) . ' permissions.')
            ->success()
            ->send();
    }
}
