<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool =>
                    $this->record->id === auth()->id()
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
}
