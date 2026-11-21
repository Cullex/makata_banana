<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchMovement extends Model
{
    protected $guarded = [];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function from()
    {
        return $this->belongsTo(StorageLocation::class, 'from_location_id');
    }

    public function to()
    {
        return $this->belongsTo(StorageLocation::class, 'to_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
