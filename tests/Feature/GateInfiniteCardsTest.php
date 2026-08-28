<?php

namespace Tests\Feature;

use App\Livewire\Gate\InfiniteVehicleCards;
use App\Livewire\Gate\InfiniteVehicleDriverCards;
use App\Models\Driver;
use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GateInfiniteCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('gate'));
    }

    public function test_vehicle_cards_filter_by_on_premises(): void
    {
        $user = User::factory()->create();

        $onPremises = Vehicle::query()->create(['number' => 'KAA 111A', 'active' => true]);
        $outside = Vehicle::query()->create(['number' => 'KBB 222B', 'active' => true]);

        GateLog::query()->create([
            'vehicle_id' => $onPremises->id,
            'vehicle_number' => 'KAA 111A',
            'driver_name' => 'John Kamau',
            'gated_in_at' => now()->subHour(),
            'gated_in_by' => $user->id,
            'status' => GateLog::STATUS_IN,
        ]);

        Livewire::actingAs($user)
            ->test(InfiniteVehicleCards::class)
            ->assertSee('KAA 111A')
            ->assertSee('KBB 222B')
            ->set('onPremises', '1')
            ->assertSee('KAA 111A')
            ->assertDontSee('KBB 222B')
            ->set('onPremises', '0')
            ->assertDontSee('KAA 111A')
            ->assertSee('KBB 222B');
    }

    public function test_vehicle_driver_cards_filter_by_active(): void
    {
        $user = User::factory()->create();

        $vehicle1 = Vehicle::query()->create(['number' => 'KCC 333C', 'active' => true]);
        $vehicle2 = Vehicle::query()->create(['number' => 'KDD 444D', 'active' => true]);

        $driver1 = Driver::query()->create(['name' => 'George Otieno']);
        $driver2 = Driver::query()->create(['name' => 'Hannah Njeri']);

        $active = VehicleDriver::query()->create([
            'vehicle_id' => $vehicle1->id,
            'driver_id' => $driver1->id,
            'active' => true,
        ]);

        $inactive = VehicleDriver::query()->create([
            'vehicle_id' => $vehicle2->id,
            'driver_id' => $driver2->id,
            'active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InfiniteVehicleDriverCards::class)
            ->assertSee('KCC 333C')
            ->assertSee('KDD 444D')
            ->set('active', '1')
            ->assertSee('KCC 333C')
            ->assertDontSee('KDD 444D')
            ->set('active', '0')
            ->assertDontSee('KCC 333C')
            ->assertSee('KDD 444D');
    }
}
