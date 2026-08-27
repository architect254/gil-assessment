<?php

namespace App\Filament\Gate\Resources\GateEntries\Schemas;

use App\Models\Driver;
use App\Models\Vehicle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GateEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->label('Vehicle Registration No.')
                    ->options(fn (): array => Vehicle::query()
                        ->where('active', true)
                        ->whereHas('assignments', fn ($query) => $query->where('active', true))
                        ->orderBy('number')
                        ->pluck('number', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->exists('vehicles', 'id')
                    ->helperText('Only vehicles with an assigned driver. Search registered plates, e.g. KAA 123A')
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $driver = Vehicle::find($get('vehicle_id'))?->currentAssignment?->driver;

                        $set('driver_id', $driver?->id ?? null);
                        $set('driver_name', $driver?->name ?? null);
                        $set('driver_id_number', $driver?->id_number ?? null);
                        $set('driver_phone', $driver?->phone ?? null);
                    }),

                Select::make('driver_id')
                    ->label('Driver Name')
                    ->options(fn (): array => Driver::query()
                        ->whereHas('vehicleAssignments', fn ($query) => $query->where('active', true))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->exists('drivers', 'id')
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $driver = Driver::find($get('driver_id'));

                        $set('driver_name', $driver?->name ?? null);
                        $set('driver_id_number', $driver?->id_number ?? null);
                        $set('driver_phone', $driver?->phone ?? null);

                        $activeVehicles = $driver?->vehicleAssignments()
                            ->where('active', true)
                            ->get();

                        if ($activeVehicles?->count() === 1) {
                            $set('vehicle_id', $activeVehicles->first()->vehicle_id);
                        }
                    }),

                Hidden::make('driver_name')
                    ->dehydrated(),

                TextInput::make('driver_id_number')
                    ->label('Driver ID / Passport No.')
                    ->maxLength(50)
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('driver_phone')
                    ->label('Driver Phone')
                    ->tel()
                    ->maxLength(30)
                    ->disabled()
                    ->dehydrated(),

                Textarea::make('remarks')
                    ->label('Remarks / Cargo Description')
                    ->columnSpanFull(),
            ]);
    }
}
