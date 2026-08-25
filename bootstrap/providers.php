<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\GatePanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    GatePanelProvider::class,
];
