<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Pages;

use App\Filament\Gate\Resources\VehicleDrivers\VehicleDriverResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleDriver extends EditRecord
{
    protected static string $resource = VehicleDriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
