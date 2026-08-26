<?php

namespace App\Filament\Gate\Resources\Vehicles;

use App\Filament\Gate\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Gate\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Gate\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Gate\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->label('Registration No.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(30)
                    ->extraInputAttributes(['class' => 'uppercase'])
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper($state))
                    ->mutateDehydratedStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper($state)),
                Textarea::make('description')
                    ->label('Description (make, colour, company)')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
