<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class GateSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            ['number' => 'KAA 123A', 'description' => 'Toyota Hilux - White'],
            ['number' => 'KBB 456B', 'description' => 'Isuzu FRR Truck'],
            ['number' => 'KCC 789C', 'description' => 'Mitsubishi Canter'],
            ['number' => 'KDD 012D', 'description' => 'Nissan UD Trailer'],
            ['number' => 'KEE 345E', 'description' => 'Toyota Hiace Van'],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::query()->updateOrCreate(['number' => $vehicle['number']], $vehicle);
        }

        $drivers = [
            ['name' => 'Ali Hassan', 'id_number' => '28471923', 'phone' => '+254711000111'],
            ['name' => 'Brian Otieno', 'id_number' => '31294857', 'phone' => '+254711222333'],
            ['name' => 'Charles Mutua', 'id_number' => '29837102', 'phone' => '+254711444555'],
            ['name' => 'David Kiprotich', 'id_number' => '33445566', 'phone' => '+254711666777'],
        ];

        foreach ($drivers as $driver) {
            Driver::query()->updateOrCreate(['name' => $driver['name']], $driver);
        }

        $assignments = [
            ['KAA 123A', 'Ali Hassan'],
            ['KBB 456B', 'Brian Otieno'],
            ['KCC 789C', 'Charles Mutua'],
            ['KDD 012D', 'David Kiprotich'],
        ];

        foreach ($assignments as [$plate, $driverName]) {
            $vehicle = Vehicle::query()->where('number', $plate)->first();
            $driver = Driver::query()->where('name', $driverName)->first();

            if ($vehicle && $driver) {
                \App\Models\VehicleDriver::query()->updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id],
                    ['active' => true],
                );
            }
        }
    }
}
