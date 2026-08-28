<?php

namespace App\Filament\Gate\Resources\Vehicles\Pages;

use App\Filament\Gate\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                Livewire::make('gate.infinite-vehicle-cards')
                    ->key('vehicle-cards')
                    ->extraAttributes(['class' => 'sm:hidden']),
            ]);
    }
}
