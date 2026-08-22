<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vehicle_id',
    'vehicle_number',
    'driver_name',
    'driver_id_number',
    'driver_phone',
    'gated_in_at',
    'gated_in_by',
    'gated_out_at',
    'gated_out_by',
    'status',
    'remarks',
])]
class GateLog extends Model
{
    use HasFactory;

    public const STATUS_IN = 'in';

    public const STATUS_OUT = 'out';

    protected function casts(): array
    {
        return [
            'gated_in_at' => 'datetime',
            'gated_out_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function gatedInUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gated_in_by');
    }

    public function gatedOutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gated_out_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_IN;
    }
}
