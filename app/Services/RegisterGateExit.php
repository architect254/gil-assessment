<?php

namespace App\Services;

use App\Models\GateLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterGateExit
{
    /**
     * Register gate exit for an existing open GateLog record.
     */
    public static function forLog(GateLog $log, ?int $userId = null): GateLog
    {
        return DB::transaction(function () use ($log, $userId): GateLog {
            $fresh = GateLog::query()->lockForUpdate()->find($log->id);

            if (! $fresh || ! $fresh->isOpen()) {
                throw new InvalidArgumentException("Vehicle {$log->vehicle_number} has already exited or log is invalid.");
            }

            $fresh->update([
                'gated_out_at' => now(),
                'gated_out_by' => $userId ?? Auth::id(),
                'status' => GateLog::STATUS_OUT,
            ]);

            return $fresh;
        });
    }

    /**
     * Register gate exit for a vehicle by vehicle ID.
     */
    public static function forVehicle(int $vehicleId, ?int $userId = null): GateLog
    {
        return DB::transaction(function () use ($vehicleId, $userId): GateLog {
            $openLog = GateLog::query()
                ->where('vehicle_id', $vehicleId)
                ->where('status', GateLog::STATUS_IN)
                ->latest('gated_in_at')
                ->lockForUpdate()
                ->first();

            if (! $openLog) {
                throw new InvalidArgumentException('No active gate-in entry found for this vehicle on premises.');
            }

            $openLog->update([
                'gated_out_at' => now(),
                'gated_out_by' => $userId ?? Auth::id(),
                'status' => GateLog::STATUS_OUT,
            ]);

            return $openLog;
        });
    }
}
