<?php

declare(strict_types=1);

namespace App\Filament\Resources\Navigation\Widgets;

use App\Models\NavigationMenu;
use Filament\Widgets\Widget;

class NavigationBuilderWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.resources.navigation.widgets.navigation-builder';

    protected int|string|array $columnSpan = 'full';

    public ?NavigationMenu $record = null;

    protected function getViewData(): array
    {
        return ['record' => $this->record];
    }
}
