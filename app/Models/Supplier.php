<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = ['rating' => 'float'];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
