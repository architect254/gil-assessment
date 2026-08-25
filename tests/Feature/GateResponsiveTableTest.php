<?php

namespace Tests\Feature;

use App\Filament\Gate\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Gate\Resources\GateEntries\Pages\ListGateEntries;
use App\Filament\Gate\Resources\VehicleDrivers\Pages\ListVehicleDrivers;
use App\Filament\Gate\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Driver;
use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GateResponsiveTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('gate'));
    }

    public function test_gate_entries_table_renders_columns_and_filters_by_status(): void
    {
        $user = User::factory()->create();

        $vehicle1 = Vehicle::query()->create(['number' => 'KAA 111A', 'active' => true]);
        $vehicle2 = Vehicle::query()->create(['number' => 'KBB 222B', 'active' => true]);

        $inLog = GateLog::query()->create([
            'vehicle_id' => $vehicle1->id,
            'vehicle_number' => 'KAA 111A',
            'driver_name' => 'John Kamau',
            'driver_phone' => '+254711000111',
            'gated_in_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        $outLog = GateLog::query()->create([
            'vehicle_id' => $vehicle2->id,
            'vehicle_number' => 'KBB 222B',
            'driver_name' => 'Jane Achieng',
            'driver_phone' => '+254722000222',
            'gated_in_at' => now()->subHours(3),
            'gated_out_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'gated_out_by' => $user->id,
            'status' => GateLog::STATUS_OUT,
        ]);

        Livewire::actingAs($user)
            ->test(ListGateEntries::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$inLog, $outLog])
            ->assertTableColumnExists('vehicle_number')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('driver_name')
            ->assertTableColumnExists('driver_phone')
            ->assertTableColumnExists('gated_in_at')
            ->assertTableColumnExists('gated_out_at')
            ->filterTable('status', GateLog::STATUS_IN)
            ->assertCanSeeTableRecords([$inLog])
            ->assertCanNotSeeTableRecords([$outLog]);
    }

    public function test_gate_entries_table_register_exit_action(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create(['number' => 'KCC 333C', 'active' => true]);

        $log = GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KCC 333C',
            'driver_name' => 'Peter Oloo',
            'gated_in_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        Livewire::actingAs($user)
            ->test(ListGateEntries::class)
            ->callTableAction('registerExit', $log);

        $this->assertSame(GateLog::STATUS_OUT, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->gated_out_at);
        $this->assertSame($user->id, $log->fresh()->gated_out_by);
    }

    public function test_vehicles_table_renders_columns_and_filters_on_premises(): void
    {
        $user = User::factory()->create();

        $vehicle1 = Vehicle::query()->create(['number' => 'KDD 444D', 'description' => 'White Canter', 'active' => true]);
        $vehicle2 = Vehicle::query()->create(['number' => 'KEE 555E', 'description' => 'Blue Trailer', 'active' => true]);

        $driver = Driver::query()->create(['name' => 'Driver One', 'phone' => '+254733000333']);
        VehicleDriver::query()->create([
            'vehicle_id' => $vehicle1->id,
            'driver_id' => $driver->id,
            'active' => true,
        ]);

        GateLog::query()->create([
            'vehicle_id' => $vehicle1->id,
            'vehicle_number' => 'KDD 444D',
            'driver_name' => 'Driver One',
            'gated_in_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        Livewire::actingAs($user)
            ->test(ListVehicles::class)
            ->assertCanSeeTableRecords([$vehicle1, $vehicle2])
            ->assertTableColumnExists('number')
            ->assertTableColumnExists('description')
            ->assertTableColumnExists('currentAssignment.driver.name')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('visits_count')
            ->filterTable('on_premises', true)
            ->assertCanSeeTableRecords([$vehicle1])
            ->assertCanNotSeeTableRecords([$vehicle2]);
    }

    public function test_drivers_table_renders_columns_and_searches(): void
    {
        $user = User::factory()->create();

        $driver1 = Driver::query()->create(['name' => 'David Mwangi', 'id_number' => '11223344', 'phone' => '+254744000444']);
        $driver2 = Driver::query()->create(['name' => 'Faith Wambui', 'id_number' => '55667788', 'phone' => '+254755000555']);

        Livewire::actingAs($user)
            ->test(ListDrivers::class)
            ->assertCanSeeTableRecords([$driver1, $driver2])
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('id_number')
            ->assertTableColumnExists('phone')
            ->assertTableColumnExists('vehicles_count')
            ->searchTable('David')
            ->assertCanSeeTableRecords([$driver1])
            ->assertCanNotSeeTableRecords([$driver2]);
    }

    public function test_vehicle_drivers_table_renders_columns_and_filters_active(): void
    {
        $user = User::factory()->create();

        $vehicle1 = Vehicle::query()->create(['number' => 'KFF 666F', 'active' => true]);
        $vehicle2 = Vehicle::query()->create(['number' => 'KGG 777G', 'active' => true]);

        $driver1 = Driver::query()->create(['name' => 'George Otieno']);
        $driver2 = Driver::query()->create(['name' => 'Hannah Njeri']);

        $assignment1 = VehicleDriver::query()->create([
            'vehicle_id' => $vehicle1->id,
            'driver_id' => $driver1->id,
            'active' => true,
        ]);

        $assignment2 = VehicleDriver::query()->create([
            'vehicle_id' => $vehicle2->id,
            'driver_id' => $driver2->id,
            'active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ListVehicleDrivers::class)
            ->assertCanSeeTableRecords([$assignment1, $assignment2])
            ->assertTableColumnExists('vehicle.number')
            ->assertTableColumnExists('vehicle.description')
            ->assertTableColumnExists('driver.name')
            ->assertTableColumnExists('driver.phone')
            ->assertTableColumnExists('active')
            ->filterTable('active', true)
            ->assertCanSeeTableRecords([$assignment1])
            ->assertCanNotSeeTableRecords([$assignment2]);
    }
}
