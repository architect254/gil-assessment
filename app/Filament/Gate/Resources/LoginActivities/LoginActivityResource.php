<?php

namespace App\Filament\Gate\Resources\LoginActivities;

use App\Filament\Gate\Resources\LoginActivities\Pages\ListLoginActivities;
use App\Filament\Gate\Resources\LoginActivities\Tables\LoginActivitiesTable;
use App\Models\LoginActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoginActivityResource extends Resource
{
    protected static ?string $model = LoginActivity::class;

    protected static ?string $modelLabel = 'Login Activity';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return LoginActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginActivities::route('/'),
        ];
    }
}
