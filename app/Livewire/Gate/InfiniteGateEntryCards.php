<?php

namespace App\Livewire\Gate;

use App\Concerns\InteractsWithInfiniteCards;
use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use App\Models\GateLog;
use App\Services\RegisterGateExit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InfiniteGateEntryCards extends Component
{
    use InteractsWithInfiniteCards;

    public ?string $activeTab = 'on_premises';

    public string $search = '';

    #[Computed]
    public function records()
    {
        return GateEntryResource::getEloquentQuery()
            ->when($this->activeTab === 'on_premises', fn ($query) => $query->where('status', 'in'))
            ->when($this->activeTab === 'exited', fn ($query) => $query->where('status', 'out'))
            ->when(filled($this->search), function ($query) {
                $query->where(fn ($q) => $q
                    ->where('vehicle_number', 'like', "%{$this->search}%")
                    ->orWhere('driver_name', 'like', "%{$this->search}%"));
            })
            ->latest('gated_in_at')
            ->cursorPaginate($this->perPage);
    }

    public function registerExit(int $logId): void
    {
        $log = GateLog::query()->find($logId);

        if (! $log?->isOpen()) {
            Notification::make()
                ->danger()
                ->title('Unable to register exit')
                ->body('This vehicle has already exited or the log is invalid.')
                ->send();

            return;
        }

        RegisterGateExit::forLog($log, Auth::id());

        Notification::make()
            ->success()
            ->title('Exit registered')
            ->send();
    }

    public function render()
    {
        return view('livewire.gate.infinite-gate-entry-cards', [
            'records' => $this->records,
        ]);
    }
}
