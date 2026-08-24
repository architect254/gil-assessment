<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_pages_require_authentication(): void
    {
        $this->get('/gate/gate-entries')->assertRedirect();
        $this->get('/gate/vehicles')->assertRedirect();
    }

    public function test_authenticated_user_can_view_gate_entries(): void
    {
        $user = User::factory()->create();

        Vehicle::query()->create(['number' => 'KAA 123A', 'active' => true]);
        Driver::query()->create(['name' => 'Ali Hassan']);

        GateLog::query()->create([
            'vehicle_id' => Vehicle::first()->id,
            'vehicle_number' => 'KAA 123A',
            'driver_name' => 'Ali Hassan',
            'gated_in_at' => now(),
            'status' => GateLog::STATUS_IN,
        ]);

        $this->actingAs($user)
            ->get('/gate/gate-entries')
            ->assertOk()
            ->assertSee('KAA 123A');
    }

    public function test_open_log_reports_on_premises_and_closes_on_exit(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create(['number' => 'KDB 456B', 'active' => true]);

        $log = GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KDB 456B',
            'driver_name' => 'Grace Njeri',
            'gated_in_at' => now(),
            'status' => GateLog::STATUS_IN,
        ]);

        $this->assertTrue($log->isOpen());

        $log->update([
            'gated_out_at' => now(),
            'gated_out_by' => $user->id,
            'status' => GateLog::STATUS_OUT,
        ]);

        $this->assertFalse($log->fresh()->isOpen());
        $this->assertSame($user->id, $log->gated_out_by);
    }

    public function test_gate_in_create_page_renders_with_assigned_vehicle(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create(['number' => 'KZZ 999Z', 'active' => true]);
        $driver = Driver::query()->create(['name' => 'Test Driver', 'id_number' => '12345678', 'phone' => '+254700111222']);

        VehicleDriver::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get('/gate/gate-entries/create')
            ->assertOk();
    }

    public function test_vehicle_current_assignment_resolves_active_driver(): void
    {
        $vehicle = Vehicle::query()->create(['number' => 'KYY 888Y', 'active' => true]);

        $this->assertNull($vehicle->currentAssignment()->first());

        $driver = Driver::query()->create(['name' => 'Assigned Driver']);

        VehicleDriver::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'active' => true,
        ]);

        $this->assertSame($driver->id, $vehicle->currentAssignment->driver->id);
    }

    public function test_vehicle_page_lists_registered_plates_with_visit_count(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create(['number' => 'KCX 789C', 'description' => 'Isuzu truck, white', 'active' => true]);

        GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KCX 789C',
            'driver_name' => 'Peter Otieno',
            'gated_in_at' => now()->subHour(),
            'gated_out_at' => now(),
            'status' => GateLog::STATUS_OUT,
        ]);

        $this->actingAs($user)
            ->get('/gate/vehicles')
            ->assertOk()
            ->assertSee('KCX 789C')
            ->assertSee('1');
    }
}
