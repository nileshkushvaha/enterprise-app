<?php

namespace App\Filament\Resources\Faq\Pages;

use App\Filament\Resources\Faq\FaqResource;
use App\Services\Faq\FaqService;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    protected static string $resource = FaqResource::class;

    protected function afterCreate(): void
    {
        app(FaqService::class)->clearCache();
    }
}
