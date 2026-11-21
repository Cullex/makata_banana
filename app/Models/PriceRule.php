<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRule extends Model
{
    protected $guarded = [];

    protected $casts = ['price_per_kg' => 'float', 'discount_pct' => 'float'];

    protected $appends = ['effective_price'];

    public function getEffectivePriceAttribute(): float
    {
        return round($this->price_per_kg * (1 - $this->discount_pct / 100), 2);
    }
}
