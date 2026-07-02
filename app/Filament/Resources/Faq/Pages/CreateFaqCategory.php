<?php

namespace App\Filament\Resources\Faq\Pages;

use App\Filament\Resources\Faq\FaqCategoryResource;
use App\Services\Faq\FaqService;
use Filament\Resources\Pages\CreateRecord;

class CreateFaqCategory extends CreateRecord
{
    protected static string $resource = FaqCategoryResource::class;

    protected function afterCreate(): void
    {
        app(FaqService::class)->clearCache();
    }
}
