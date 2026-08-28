<?php

namespace App\Livewire\Gate;

use App\Concerns\InteractsWithInfiniteCards;
use App\Filament\Gate\Resources\Vehicles\VehicleResource;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InfiniteVehicleCards extends Component
{
    use InteractsWithInfiniteCards;

    public string $search = '';

    public ?string $onPremises = null;

    public function updatedOnPremises(): void
    {
        $this->perPage = 12;
    }

    #[Computed]
    public function records()
    {
        return VehicleResource::getEloquentQuery()
            ->with(['currentAssignment.driver'])
            ->withExists(['gateLogs as on_premises' => fn ($query) => $query->where('status', 'in')])
            ->when(filled($this->search), fn ($query) => $query
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->onPremises === '1', fn ($query) => $query
                ->whereHas('gateLogs', fn ($q) => $q->where('status', 'in')))
            ->when($this->onPremises === '0', fn ($query) => $query
                ->whereDoesntHave('gateLogs', fn ($q) => $q->where('status', 'in')))
            ->orderBy('number')
            ->cursorPaginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.gate.infinite-vehicle-cards', [
            'records' => $this->records,
        ]);
    }
}
