<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RegisterGateExit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GateOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_gate_exit_service_closes_open_gate_log(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create(['number' => 'KBZ 999Z', 'active' => true]);

        $log = GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KBZ 999Z',
            'driver_name' => 'John Doe',
            'driver_id_number' => '12345678',
            'driver_phone' => '+254700112233',
            'gated_in_at' => now()->subHours(2),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        $this->assertTrue($log->isOpen());

        $closedLog = RegisterGateExit::forLog($log, $user->id);

        $this->assertSame(GateLog::STATUS_OUT, $closedLog->status);
        $this->assertNotNull($closedLog->gated_out_at);
        $this->assertSame($user->id, $closedLog->gated_out_by);
        $this->assertFalse($closedLog->isOpen());
    }

    public function test_register_gate_exit_by_vehicle_finds_and_closes_active_entry(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create(['number' => 'KDD 444D', 'active' => true]);

        $log = GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KDD 444D',
            'driver_name' => 'Jane Mwangi',
            'gated_in_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        $closed = RegisterGateExit::forVehicle($vehicle->id, $user->id);

        $this->assertSame($log->id, $closed->id);
        $this->assertSame(GateLog::STATUS_OUT, $closed->status);
        $this->assertNotNull($closed->gated_out_at);
    }

    public function test_register_gate_exit_throws_exception_on_double_exit(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create(['number' => 'KEE 555E', 'active' => true]);

        $log = GateLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => 'KEE 555E',
            'driver_name' => 'Mark Kamau',
            'gated_in_at' => now()->subHour(),
            'gated_out_at' => now(),
            'gated_out_by' => $user->id,
            'status' => GateLog::STATUS_OUT,
        ]);

        $this->expectException(InvalidArgumentException::class);
        RegisterGateExit::forLog($log, $user->id);
    }

    public function test_register_gate_exit_for_vehicle_not_on_premises_throws_exception(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create(['number' => 'KFF 666F', 'active' => true]);

        $this->expectException(InvalidArgumentException::class);
        RegisterGateExit::forVehicle($vehicle->id, $user->id);
    }
}
