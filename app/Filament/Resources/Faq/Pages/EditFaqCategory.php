<?php

namespace App\Filament\Resources\Faq\Pages;

use App\Filament\Resources\Faq\FaqCategoryResource;
use App\Services\Faq\FaqService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFaqCategory extends EditRecord
{
    protected static string $resource = FaqCategoryResource::class;

    protected function afterSave(): void
    {
        app(FaqService::class)->clearCache();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
