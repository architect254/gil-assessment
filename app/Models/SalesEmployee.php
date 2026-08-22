<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'phone'])]
class SalesEmployee extends Model
{
    use HasFactory;

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
