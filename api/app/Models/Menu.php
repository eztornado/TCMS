<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['slug', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('position');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('position');
    }
}
