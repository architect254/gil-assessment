<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Schemas;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleDriver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class VehicleDriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->label('Vehicle')
                    ->options(fn () => Vehicle::query()->orderBy('number')->pluck('number', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        table: VehicleDriver::class,
                        column: 'vehicle_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('active', true),
                    )
                    ->validationMessages([
                        'unique' => 'This vehicle already has an active driver assignment.',
                    ]),
                Select::make('driver_id')
                    ->label('Driver')
                    ->options(fn () => Driver::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('active')
                    ->required()
                    ->default(true)
                    ->helperText('Only one active assignment per vehicle is allowed.'),
            ])
            ->columns(2);
    }
}
