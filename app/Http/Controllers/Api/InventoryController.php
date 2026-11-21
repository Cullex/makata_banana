<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchMovement;
use App\Models\Loss;
use App\Models\StorageLocation;
use App\Services\Warehouse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function summary()
    {
        Warehouse::autoAge();
        $stock = Batch::inStock()->get();

        $matrix = collect(StorageLocation::ZONES)->except('dispatch')->map(function ($label, $zone) use ($stock) {
            $status = $zone === 'receiving' ? 'receiving' : $zone;
            $rows = $stock->where('status', $status);

            return [
                'zone' => $zone, 'label' => $label, 'kg' => (int) $rows->sum('remaining_kg'), 'batches' => $rows->count(),
                'stages' => collect(Batch::STAGES)->mapWithKeys(fn ($s) => [$s => (int) $rows->where('stage', $s)->sum('remaining_kg')]),
            ];
        })->values();

        return [
            'matrix' => $matrix,
            'totals' => collect(Batch::STAGES)->mapWithKeys(fn ($s) => [$s => (int) $stock->where('stage', $s)->sum('remaining_kg')]),
            'value' => round($stock->sum(fn ($b) => $b->remaining_kg * $b->cost_per_kg), 2),
            'fifo' => Batch::where('status', 'sales')->where('remaining_kg', '>', 0)->fifo()->with('supplier:id,name', 'location:id,code,name')->get(),
        ];
    }

    public function movements(Request $request)
    {
        return BatchMovement::with(['batch:id,code', 'from:id,code,name', 'to:id,code,name', 'user:id,name'])
            ->latest()->paginate((int) $request->query('per_page', 20));
    }

    public function losses(Request $request)
    {
        return [
            'items' => Loss::with(['batch:id,code,supplier_id', 'batch.supplier:id,name', 'user:id,name'])->latest()->paginate(15),
            'by_reason' => Loss::where('created_at', '>=', now()->subDays(30))->selectRaw('reason, SUM(quantity_kg) kg, COUNT(*) n')->groupBy('reason')->get(),
        ];
    }
}
