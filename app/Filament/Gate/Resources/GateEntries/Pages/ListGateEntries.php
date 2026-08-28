<?php

namespace App\Filament\Gate\Resources\GateEntries\Pages;

use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use App\Models\GateLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListGateEntries extends ListRecords
{
    protected static string $resource = GateEntryResource::class;

    public ?string $activeTab = 'on_premises';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Register Gate In')
                ->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->url(static::$resource::getUrl('create')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'on_premises' => Tab::make('On Premises')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', GateLog::STATUS_IN))
                ->badge(GateLog::query()->where('status', GateLog::STATUS_IN)->count())
                ->badgeColor('warning'),
            'exited' => Tab::make('Exited')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', GateLog::STATUS_OUT)),
            'all' => Tab::make('All'),
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
                Livewire::make(
                    'gate.infinite-gate-entry-cards',
                    ['activeTab' => $this->activeTab ?? 'on_premises'],
                )
                    ->key('gate-cards-' . ($this->activeTab ?? 'on_premises'))
                    ->extraAttributes(['class' => 'sm:hidden']),
            ]);
    }
}
