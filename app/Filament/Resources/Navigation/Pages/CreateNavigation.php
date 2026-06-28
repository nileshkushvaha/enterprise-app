<?php

declare(strict_types=1);

namespace App\Filament\Resources\Navigation\Pages;

use App\Filament\Resources\Navigation\NavigationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNavigation extends CreateRecord
{
    protected static string $resource = NavigationResource::class;
}
