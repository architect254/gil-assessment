<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Pages;

use App\Filament\Gate\Resources\VehicleDrivers\VehicleDriverResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVehicleDrivers extends ListRecords
{
    protected static string $resource = VehicleDriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add Assignment')
                ->icon('heroicon-o-plus')
                ->url(static::$resource::getUrl('create')),
        ];
    }
}
