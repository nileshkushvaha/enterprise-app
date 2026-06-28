<?php

declare(strict_types=1);

namespace App\Filament\Resources\Navigation\Pages;

use App\Filament\Resources\Navigation\NavigationResource;
use App\Filament\Resources\Navigation\Widgets\NavigationBuilderWidget;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNavigation extends EditRecord
{
    protected static string $resource = NavigationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            NavigationBuilderWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return array_merge(parent::getWidgetData(), [
            'record' => $this->getRecord(),
        ]);
    }
}
