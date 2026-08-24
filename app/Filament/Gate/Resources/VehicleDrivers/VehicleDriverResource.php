<?php

namespace App\Filament\Gate\Resources\VehicleDrivers;

use App\Filament\Gate\Resources\VehicleDrivers\Pages\CreateVehicleDriver;
use App\Filament\Gate\Resources\VehicleDrivers\Pages\EditVehicleDriver;
use App\Filament\Gate\Resources\VehicleDrivers\Pages\ListVehicleDrivers;
use App\Filament\Gate\Resources\VehicleDrivers\Schemas\VehicleDriverForm;
use App\Filament\Gate\Resources\VehicleDrivers\Tables\VehicleDriversTable;
use App\Models\VehicleDriver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleDriverResource extends Resource
{
    protected static ?string $model = VehicleDriver::class;

    protected static ?string $modelLabel = 'Vehicle Driver Assignment';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return VehicleDriverForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleDriversTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleDrivers::route('/'),
            'create' => CreateVehicleDriver::route('/create'),
            'edit' => EditVehicleDriver::route('/{record}/edit'),
        ];
    }
}
