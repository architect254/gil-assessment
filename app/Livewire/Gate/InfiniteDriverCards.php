<?php

namespace App\Livewire\Gate;

use App\Concerns\InteractsWithInfiniteCards;
use App\Filament\Gate\Resources\Drivers\DriverResource;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InfiniteDriverCards extends Component
{
    use InteractsWithInfiniteCards;

    public string $search = '';

    #[Computed]
    public function records()
    {
        return DriverResource::getEloquentQuery()
            ->withCount('vehicles')
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('id_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->cursorPaginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.gate.infinite-driver-cards', [
            'records' => $this->records,
        ]);
    }
}
