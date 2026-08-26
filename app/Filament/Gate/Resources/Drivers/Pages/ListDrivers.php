<?php

namespace App\Filament\Gate\Resources\Drivers\Pages;

use App\Filament\Gate\Resources\Drivers\DriverResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add Driver')
                ->icon('heroicon-o-plus')
                ->url(static::$resource::getUrl('create')),
        ];
    }
}
