<?php

namespace App\Livewire\Gate;

use App\Concerns\InteractsWithInfiniteCards;
use App\Filament\Gate\Resources\VehicleDrivers\VehicleDriverResource;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InfiniteVehicleDriverCards extends Component
{
    use InteractsWithInfiniteCards;

    public string $search = '';

    public ?string $active = null;

    public function updatedActive(): void
    {
        $this->perPage = 12;
    }

    #[Computed]
    public function records()
    {
        return VehicleDriverResource::getEloquentQuery()
            ->with(['vehicle', 'driver'])
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereHas('vehicle', fn ($v) => $v->where('number', 'like', "%{$this->search}%"))
                    ->orWhereHas('driver', fn ($d) => $d->where('name', 'like', "%{$this->search}%"))))
            ->when($this->active === '1', fn ($query) => $query->where('active', true))
            ->when($this->active === '0', fn ($query) => $query->where('active', false))
            ->orderBy('vehicle_id')
            ->cursorPaginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.gate.infinite-vehicle-driver-cards', [
            'records' => $this->records,
        ]);
    }
}
