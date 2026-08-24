<?php

namespace App\Filament\Gate\Resources\Drivers\Pages;

use App\Filament\Gate\Resources\Drivers\DriverResource;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;
}
