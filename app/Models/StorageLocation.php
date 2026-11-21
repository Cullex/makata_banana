<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageLocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_temp' => 'float',
        'target_humidity' => 'float',
        'target_ethylene' => 'integer',
    ];

    public const ZONES = [
        'receiving' => 'Receiving Area',
        'pre_ripening' => 'Pre-Ripening Storage',
        'ripening' => 'Ripening Rooms',
        'sales' => 'Sales Storage',
        'dispatch' => 'Dispatch / Loading',
    ];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function readings()
    {
        return $this->hasMany(RoomReading::class);
    }

    public function latestReading()
    {
        return $this->hasOne(RoomReading::class)->latestOfMany('recorded_at');
    }

    /**
     * A "live" sensor value for the demo: drifts gently around the set point.
     */
    public function liveReading(): array
    {
        $t = now()->timestamp / 15;
        $seed = $this->id * 7.3;
        $temp = ($this->target_temp ?? 14) + sin($t / 3 + $seed) * 0.4 + sin($t / 11 + $seed) * 0.2;
        $hum = ($this->target_humidity ?? 90) + sin($t / 5 + $seed) * 1.2;
        $eth = $this->status === 'gassing'
            ? max(0, ($this->target_ethylene ?? 0) + sin($t / 4 + $seed) * 8)
            : max(0, sin($t / 6 + $seed) * 2 + 2);

        return [
            'temperature' => round($temp, 1),
            'humidity' => round(min(99, $hum), 1),
            'ethylene' => (int) round($eth),
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
