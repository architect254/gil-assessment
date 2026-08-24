<?php

namespace App\Filament\Gate\Resources\GateEntries;

use App\Filament\Gate\Resources\GateEntries\Pages\CreateGateEntry;
use App\Filament\Gate\Resources\GateEntries\Pages\ListGateEntries;
use App\Filament\Gate\Resources\GateEntries\Schemas\GateEntryForm;
use App\Filament\Gate\Resources\GateEntries\Tables\GateEntriesTable;
use App\Models\GateLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GateEntryResource extends Resource
{
    protected static ?string $model = GateLog::class;

    protected static ?string $modelLabel = 'Gate Entry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightStartOnRectangle;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return GateEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GateEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGateEntries::route('/'),
            'create' => CreateGateEntry::route('/create'),
        ];
    }
}
