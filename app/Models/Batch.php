<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'arrived_at' => 'datetime',
        'ripening_started_at' => 'datetime',
        'expected_ripe_at' => 'datetime',
        'ripe_at' => 'datetime',
        'depleted_at' => 'datetime',
        'cost_per_kg' => 'float',
    ];

    protected $appends = ['age_days', 'shelf_days_left', 'ripening_progress'];

    public const STAGES = ['green', 'turning', 'ripe', 'overripe'];

    public const STATUSES = ['receiving', 'pre_ripening', 'ripening', 'sales', 'depleted', 'spoiled'];

    /** Days a ripe banana stays saleable before it is considered overripe / spoiling. */
    public const RIPE_SHELF_DAYS = 3;

    public const OVERRIPE_SHELF_DAYS = 3;

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location()
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class)->latest();
    }

    public function movements()
    {
        return $this->hasMany(BatchMovement::class)->latest();
    }

    public function losses()
    {
        return $this->hasMany(Loss::class)->latest();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeInStock($q)
    {
        return $q->whereNotIn('status', ['depleted', 'spoiled'])->where('remaining_kg', '>', 0);
    }

    /** Oldest ripe stock first — the FIFO rule for sales. */
    public function scopeFifo($q)
    {
        return $q->orderByRaw('COALESCE(ripe_at, arrived_at) asc')->orderBy('arrived_at');
    }

    public function getAgeDaysAttribute(): ?float
    {
        return $this->arrived_at ? round($this->arrived_at->diffInHours(now()) / 24, 1) : null;
    }

    public function getShelfDaysLeftAttribute(): ?float
    {
        if (! $this->ripe_at || in_array($this->status, ['depleted', 'spoiled'])) {
            return null;
        }
        $end = $this->ripe_at->copy()->addDays(self::RIPE_SHELF_DAYS + self::OVERRIPE_SHELF_DAYS);

        return round(now()->diffInHours($end, false) / 24, 1);
    }

    public function getRipeningProgressAttribute(): int
    {
        return match ($this->stage) {
            'ripe' => 100,
            'overripe' => 100,
            default => $this->ripening_started_at && $this->expected_ripe_at
                ? (int) max(5, min(95, $this->ripening_started_at->diffInMinutes(now()) / max(1, $this->ripening_started_at->diffInMinutes($this->expected_ripe_at)) * 100))
                : ($this->stage === 'turning' ? 55 : 0),
        };
    }
}
