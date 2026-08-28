<?php

namespace App\Filament\Gate\Resources\Drivers\Pages;

use App\Filament\Gate\Resources\Drivers\DriverResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                Livewire::make('gate.infinite-driver-cards')
                    ->key('driver-cards')
                    ->extraAttributes(['class' => 'sm:hidden']),
            ]);
    }
}
