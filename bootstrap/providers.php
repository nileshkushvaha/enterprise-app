<?php

use App\Providers\AppServiceProvider;
use App\Providers\CmsServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\NavigationServiceProvider;

return [
    AppServiceProvider::class,
    CmsServiceProvider::class,
    NavigationServiceProvider::class,
    AdminPanelProvider::class,
];
