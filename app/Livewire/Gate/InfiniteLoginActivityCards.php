<?php

namespace App\Livewire\Gate;

use App\Concerns\InteractsWithInfiniteCards;
use App\Filament\Gate\Resources\LoginActivities\LoginActivityResource;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InfiniteLoginActivityCards extends Component
{
    use InteractsWithInfiniteCards;

    public string $search = '';

    #[Computed]
    public function records()
    {
        return LoginActivityResource::getEloquentQuery()
            ->with('user')
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->search}%"))
                    ->orWhere('ip_address', 'like', "%{$this->search}%")))
            ->latest('logged_in_at')
            ->cursorPaginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.gate.infinite-login-activity-cards', [
            'records' => $this->records,
        ]);
    }
}
