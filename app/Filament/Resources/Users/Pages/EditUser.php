<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Snapshot of role names before the form is saved. */
    public array $oldRoles = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->id === auth()->id()
                    || $this->record->hasRole('super_admin')
                ),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Do not update password if left blank during edit
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // password_confirmation is a virtual field only used for validation
        unset($data['password_confirmation']);

        return $data;
    }

    protected function beforeSave(): void
    {
        // Capture roles before Filament syncs the relationship so we can diff in afterSave.
        $this->oldRoles = $this->record->roles->pluck('name')->toArray();
    }

    protected function afterSave(): void
    {
        $newRoles = $this->record->fresh()->roles->pluck('name')->toArray();

        $added = array_values(array_diff($newRoles, $this->oldRoles));
        $removed = array_values(array_diff($this->oldRoles, $newRoles));

        if ($added || $removed) {
            activity('users')
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->event('roles_updated')
                ->withProperties([
                    'roles_added' => $added,
                    'roles_removed' => $removed,
                    'current_roles' => $newRoles,
                ])
                ->log('User roles updated');
        }
    }
}
