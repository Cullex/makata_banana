<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomReading extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature' => 'float',
        'humidity' => 'float',
    ];
}
