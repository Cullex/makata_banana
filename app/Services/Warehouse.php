<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchMovement;
use App\Models\Loss;
use App\Models\OrderItem;
use App\Models\PriceRule;
use App\Models\StorageLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Warehouse
{
    /**
     * Age sales stock automatically: ripe -> overripe after the ripe shelf window.
     */
    public static function autoAge(): void
    {
        Batch::where('status', 'sales')->where('stage', 'ripe')
            ->where('ripe_at', '<', now()->subDays(Batch::RIPE_SHELF_DAYS))
            ->update(['stage' => 'overripe']);
    }

    public static function priceFor(Batch $batch, ?Collection $rules = null): array
    {
        $rules ??= PriceRule::all();
        $stage = $batch->stage === 'overripe' ? 'overripe' : 'ripe';
        $rule = $rules->first(fn ($r) => $r->stage === $stage && $r->grade === $batch->grade)
            ?? $rules->firstWhere('stage', $stage);

        $price = $rule?->price_per_kg ?? 1;
        $discount = $rule?->discount_pct ?? 0;

        // Clearance: less than a day of shelf life left
        if ($batch->shelf_days_left !== null && $batch->shelf_days_left < 1) {
            $discount = max($discount, 50);
        }

        return ['unit_price' => $price, 'discount_pct' => $discount];
    }

    /**
     * FIFO allocation of saleable stock for the requested order lines.
     *
     * @param  array<int, array{stage:string, grade?:string|null, quantity_kg:int}>  $lines
     */
    public static function allocate(array $lines): array
    {
        $rules = PriceRule::all();
        $allocations = [];
        $taken = [];

        foreach ($lines as $i => $line) {
            $need = (int) $line['quantity_kg'];
            $query = Batch::where('status', 'sales')->where('stage', $line['stage'])->where('remaining_kg', '>', 0);
            if (! empty($line['grade'])) {
                $query->where('grade', $line['grade']);
            }
            $batches = $query->fifo()->with('supplier:id,name')->get();
            $available = $batches->sum(fn ($b) => $b->remaining_kg - ($taken[$b->id] ?? 0));

            if ($available < $need) {
                throw ValidationException::withMessages([
                    "lines.$i.quantity_kg" => "Only ".number_format($available)." kg of {$line['stage']} stock available.",
                ]);
            }

            foreach ($batches as $batch) {
                if ($need <= 0) {
                    break;
                }
                $free = $batch->remaining_kg - ($taken[$batch->id] ?? 0);
                if ($free <= 0) {
                    continue;
                }
                $qty = min($free, $need);
                $need -= $qty;
                $taken[$batch->id] = ($taken[$batch->id] ?? 0) + $qty;
                $price = self::priceFor($batch, $rules);
                $gross = $qty * $price['unit_price'];

                $allocations[] = [
                    'line' => $i,
                    'batch_id' => $batch->id,
                    'batch_code' => $batch->code,
                    'supplier' => $batch->supplier?->name,
                    'grade' => $batch->grade,
                    'stage' => $batch->stage,
                    'ripe_at' => $batch->ripe_at?->toIso8601String(),
                    'shelf_days_left' => $batch->shelf_days_left,
                    'quantity_kg' => $qty,
                    'unit_price' => $price['unit_price'],
                    'discount_pct' => $price['discount_pct'],
                    'gross' => round($gross, 2),
                    'line_total' => round($gross * (1 - $price['discount_pct'] / 100), 2),
                ];
            }
        }

        $subtotal = array_sum(array_column($allocations, 'gross'));
        $total = array_sum(array_column($allocations, 'line_total'));

        return [
            'allocations' => $allocations,
            'quantity_kg' => array_sum(array_column($allocations, 'quantity_kg')),
            'subtotal' => round($subtotal, 2),
            'discount' => round($subtotal - $total, 2),
            'total' => round($total, 2),
        ];
    }

    public static function deduct(OrderItem $item): void
    {
        $batch = $item->batch;
        $batch->remaining_kg = max(0, $batch->remaining_kg - $item->quantity_kg);
        if ($batch->remaining_kg === 0) {
            $batch->status = 'depleted';
            $batch->depleted_at = now();
        }
        $batch->save();
    }

    public static function move(Batch $batch, string $toStatus, ?StorageLocation $to = null, ?string $note = null): BatchMovement
    {
        $movement = BatchMovement::create([
            'batch_id' => $batch->id,
            'from_location_id' => $batch->storage_location_id,
            'to_location_id' => $to?->id,
            'from_status' => $batch->status,
            'to_status' => $toStatus,
            'quantity_kg' => $batch->remaining_kg,
            'user_id' => Auth::id(),
            'note' => $note,
        ]);

        $batch->status = $toStatus;
        $batch->storage_location_id = $to?->id;
        $batch->save();

        return $movement;
    }

    public static function recordLoss(Batch $batch, int $kg, string $reason, ?string $notes = null): Loss
    {
        $kg = min($kg, $batch->remaining_kg);
        $loss = Loss::create([
            'batch_id' => $batch->id,
            'user_id' => Auth::id(),
            'quantity_kg' => $kg,
            'reason' => $reason,
            'notes' => $notes,
        ]);
        $batch->remaining_kg -= $kg;
        if ($batch->remaining_kg <= 0) {
            $batch->status = $reason === 'spoiled' ? 'spoiled' : 'depleted';
            $batch->depleted_at = now();
            $batch->storage_location_id = null;
        }
        $batch->save();

        return $loss;
    }

    public static function alerts(): Collection
    {
        $alerts = collect();

        Batch::where('status', 'ripening')->where('stage', 'ripe')->with('location:id,code,name')->get()
            ->each(fn ($b) => $alerts->push([
                'id' => "ripe-{$b->id}",
                'level' => 'success',
                'title' => "{$b->code} is ripe and ready for sale",
                'body' => number_format($b->remaining_kg)." kg in {$b->location?->name}. Move to sales storage.",
                'batch_id' => $b->id,
                'action' => 'move_to_sales',
                'at' => ($b->ripe_at ?? $b->updated_at)->toIso8601String(),
            ]));

        Batch::where('status', 'sales')->where('remaining_kg', '>', 0)->whereNotNull('ripe_at')->with('location:id,name')->get()
            ->filter(fn ($b) => $b->shelf_days_left !== null && $b->shelf_days_left <= 2)
            ->each(fn ($b) => $alerts->push([
                'id' => "spoil-{$b->id}",
                'level' => $b->shelf_days_left <= 1 ? 'danger' : 'warning',
                'title' => "{$b->code} nearing spoilage",
                'body' => number_format($b->remaining_kg).' kg '.$b->stage.', '.max(0, $b->shelf_days_left).' days of shelf life left. Prioritise or discount.',
                'batch_id' => $b->id,
                'action' => 'discount',
                'at' => now()->subHours(1)->toIso8601String(),
            ]));

        Batch::where('status', 'ripening')->where('stage', '!=', 'ripe')->where('expected_ripe_at', '<', now())->get()
            ->each(fn ($b) => $alerts->push([
                'id' => "late-{$b->id}",
                'level' => 'warning',
                'title' => "{$b->code} behind ripening schedule",
                'body' => 'Expected ripe '.$b->expected_ripe_at->diffForHumans().'. Check room conditions.',
                'batch_id' => $b->id,
                'at' => $b->expected_ripe_at->toIso8601String(),
            ]));

        Batch::where('status', 'receiving')->get()
            ->each(fn ($b) => $alerts->push([
                'id' => "inspect-{$b->id}",
                'level' => 'info',
                'title' => "{$b->code} awaiting inspection",
                'body' => number_format($b->initial_kg).' kg arrived '.$b->arrived_at->diffForHumans().'.',
                'batch_id' => $b->id,
                'action' => 'inspect',
                'at' => $b->arrived_at->toIso8601String(),
            ]));

        StorageLocation::where('zone', 'ripening')->get()->each(function ($room) use ($alerts) {
            if ($room->status === 'maintenance') {
                $alerts->push([
                    'id' => "room-{$room->id}",
                    'level' => 'warning',
                    'title' => "{$room->name} under maintenance",
                    'body' => 'Room unavailable for new batches.',
                    'room_id' => $room->id,
                    'at' => $room->updated_at->toIso8601String(),
                ]);
            } elseif ($room->target_temp && ($room->target_temp > 20 || $room->target_temp < 13)) {
                $alerts->push([
                    'id' => "room-{$room->id}",
                    'level' => 'danger',
                    'title' => "{$room->name} temperature out of range",
                    'body' => "Set point {$room->target_temp} °C. Recommended range is 13 to 20 °C.",
                    'room_id' => $room->id,
                    'at' => $room->updated_at->toIso8601String(),
                ]);
            }
        });

        $order = ['danger' => 0, 'warning' => 1, 'success' => 2, 'info' => 3];

        return $alerts->sortBy(fn ($a) => $order[$a['level']])->values();
    }
}
