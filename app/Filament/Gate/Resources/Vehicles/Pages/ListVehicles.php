<?php

namespace App\Filament\Gate\Resources\Vehicles\Pages;

use App\Filament\Gate\Resources\Vehicles\VehicleResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;
}
