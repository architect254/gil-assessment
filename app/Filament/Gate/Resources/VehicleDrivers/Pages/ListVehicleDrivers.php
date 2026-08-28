<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Pages;

use App\Filament\Gate\Resources\VehicleDrivers\VehicleDriverResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                Livewire::make('gate.infinite-vehicle-driver-cards')
                    ->key('vehicle-driver-cards')
                    ->extraAttributes(['class' => 'sm:hidden']),
            ]);
    }
}
