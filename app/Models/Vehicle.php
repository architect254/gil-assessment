<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function openGateLog(): ?GateLog
    {
        return $this->gateLogs()->where('status', 'in')->latest('gated_in_at')->first();
    }
}
