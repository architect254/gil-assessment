<?php

namespace App\Filament\Gate\Resources\LoginActivities\Pages;

use App\Filament\Gate\Resources\LoginActivities\LoginActivityResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListLoginActivities extends ListRecords
{
    protected static string $resource = LoginActivityResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                Livewire::make('gate.infinite-login-activity-cards')
                    ->key('login-activity-cards')
                    ->extraAttributes(['class' => 'sm:hidden']),
            ]);
    }
}
