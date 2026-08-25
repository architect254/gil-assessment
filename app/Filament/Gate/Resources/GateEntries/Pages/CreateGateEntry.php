<?php

namespace App\Filament\Gate\Resources\GateEntries\Pages;

use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use App\Models\GateLog;
use App\Models\Vehicle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateGateEntry extends CreateRecord
{
    protected static string $resource = GateEntryResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);

        $openLog = GateLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', GateLog::STATUS_IN)
            ->exists();

        if ($openLog) {
            $this->notifyDanger("Vehicle {$vehicle->number} is already on the premises.");

            $this->redirect(static::$resource::getUrl('index'));

            return new GateLog;
        }

        return GateLog::create([
            'vehicle_id' => $vehicle->id,
            'vehicle_number' => $vehicle->number,
            'driver_name' => $data['driver_name'],
            'driver_id_number' => $data['driver_id_number'] ?? null,
            'driver_phone' => $data['driver_phone'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'gated_in_at' => now(),
            'gated_in_by' => Auth::id(),
            'status' => GateLog::STATUS_IN,
        ]);
    }

    protected function notifyDanger(string $message): void
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }
}
