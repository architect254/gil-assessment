<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Pages;

use App\Filament\Gate\Resources\VehicleDrivers\VehicleDriverResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleDriver extends CreateRecord
{
    protected static string $resource = VehicleDriverResource::class;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}
