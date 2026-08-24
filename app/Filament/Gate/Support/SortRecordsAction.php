<?php

namespace App\Filament\Gate\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Contracts\HasTable;

class SortRecordsAction
{
    public static function make(array $columnOptions): Action
    {
        return Action::make('sortRecords')
            ->label('Sort')
            ->icon('heroicon-o-arrows-up-down')
            ->color('gray')
            ->badge(fn (HasTable $livewire): ?string => $livewire->getTableSortColumn()
                ? ($livewire->getTableSortDirection() === 'desc' ? 'desc' : 'asc')
                : null)
            ->schema([
                Select::make('column')
                    ->label('Sort by')
                    ->options($columnOptions)
                    ->placeholder('Default order')
                    ->native(false)
                    ->live(),
                ToggleButtons::make('direction')
                    ->label('Direction')
                    ->options(['asc' => 'Ascending', 'desc' => 'Descending'])
                    ->inline()
                    ->default('asc')
                    ->visible(fn (Get $get): bool => filled($get('column'))),
            ])
            ->fillForm(fn (HasTable $livewire): array => [
                'column' => $livewire->getTableSortColumn(),
                'direction' => $livewire->getTableSortDirection() ?? 'asc',
            ])
            ->modalWidth('sm')
            ->modalSubmitActionLabel('Apply sorting')
            ->action(function (array $data, HasTable $livewire): void {
                if (blank($data['column'] ?? null)) {
                    $livewire->tableSort = null;
                    $livewire->updatedTableSort();

                    return;
                }

                $livewire->sortTable($data['column'], $data['direction'] ?? 'asc');
            });
    }
}
