<?php

namespace App\Filament\Gate\Resources\Vehicles\Pages;

use App\Filament\Gate\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add Vehicle')
                ->icon('heroicon-o-plus')
                ->url(static::$resource::getUrl('create')),
        ];
    }
}
