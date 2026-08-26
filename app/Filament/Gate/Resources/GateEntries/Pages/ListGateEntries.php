<?php

namespace App\Filament\Gate\Resources\GateEntries\Pages;

use App\Filament\Gate\Pages\GateOut;
use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use App\Models\GateLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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
}
