<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'description', 'unit_price'])]
class Item extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:3',
        ];
    }
}
