<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ordered_at' => 'datetime',
        'subtotal' => 'float',
        'discount' => 'float',
        'total' => 'float',
    ];

    public const STATUSES = ['allocated', 'packed', 'dispatched', 'delivered', 'cancelled'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
