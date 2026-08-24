<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'id_number', 'phone'])]
class Driver extends Model
{
    use HasFactory;

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriver::class);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_drivers')
            ->withPivot('active')
            ->withTimestamps();
    }
}
