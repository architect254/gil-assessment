<?php

namespace App\Filament\Gate\Resources\Vehicles\Pages;

use App\Filament\Gate\Resources\Vehicles\VehicleResource;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}
