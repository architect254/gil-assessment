<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['number', 'description', 'active'])]
class Vehicle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleDriver::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(VehicleDriver::class)
            ->where('active', true)
            ->latestOfMany();
    }

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'vehicle_drivers')
            ->withPivot('active')
            ->withTimestamps();
    }

    public function openGateLog(): ?GateLog
    {
        return $this->gateLogs()->where('status', 'in')->latest('gated_in_at')->first();
    }
}
