<?php

namespace App\Models;

class TaxRate extends BaseModel
{
    protected $fillable = ['name', 'rate', 'country', 'is_default'];

    protected function casts(): array
    {
        return ['rate' => 'float', 'is_default' => 'boolean'];
    }
}
