<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Inspection;
use App\Models\StorageLocation;
use App\Services\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        Warehouse::autoAge();

        $q = Batch::with(['supplier:id,name', 'location:id,code,name,zone']);

        if ($s = $request->query('search')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%")));
        }
        if ($status = $request->query('status')) {
            $status === 'in_stock' ? $q->inStock() : $q->whereIn('status', explode(',', $status));
        }
        if ($stage = $request->query('stage')) {
            $q->whereIn('stage', explode(',', $stage));
        }
        foreach (['supplier_id', 'storage_location_id', 'grade'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }

        match ($request->query('sort', 'recent')) {
            'fifo' => $q->fifo(),
            'oldest' => $q->orderBy('arrived_at'),
            'qty' => $q->orderByDesc('remaining_kg'),
            default => $q->orderByDesc('arrived_at'),
        };

        return $q->paginate((int) $request->query('per_page', 15));
    }

    public function show(Batch $batch)
    {
        return $batch->load([
            'supplier', 'location', 'receiver:id,name',
            'inspections.user:id,name,role',
            'movements.from:id,code,name', 'movements.to:id,code,name', 'movements.user:id,name',
            'losses.user:id,name',
            'orderItems.order:id,number,customer_id,ordered_at,status', 'orderItems.order.customer:id,name',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'variety' => 'required|string|max:50',
            'initial_kg' => 'required|integer|min:50|max:50000',
            'cartons' => 'nullable|integer|min:0',
            'stage' => ['required', Rule::in(Batch::STAGES)],
            'grade' => 'required|in:A,B,C',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'arrived_at' => 'nullable|date',
            'storage_location_id' => ['nullable', Rule::exists('storage_locations', 'id')->whereIn('zone', ['receiving', 'pre_ripening'])],
            'notes' => 'nullable|string|max:1000',
            'damage_pct' => 'nullable|numeric|min:0|max:100',
            'pulp_temp' => 'nullable|numeric',
        ]);

        $batch = DB::transaction(function () use ($data, $request) {
            $location = isset($data['storage_location_id']) ? StorageLocation::find($data['storage_location_id']) : null;
            $seq = Batch::whereDate('created_at', today())->count() + 1;

            $batch = Batch::create([
                'code' => 'MB-'.now()->format('ymd').'-'.str_pad($seq, 2, '0', STR_PAD_LEFT).strtoupper(substr(md5(uniqid()), 0, 2)),
                'supplier_id' => $data['supplier_id'],
                'variety' => $data['variety'],
                'initial_kg' => $data['initial_kg'],
                'remaining_kg' => $data['initial_kg'],
                'cartons' => $data['cartons'] ?? (int) round($data['initial_kg'] / 18),
                'stage' => $data['stage'],
                'grade' => $data['grade'],
                'cost_per_kg' => $data['cost_per_kg'] ?? 0,
                'arrived_at' => $data['arrived_at'] ?? now(),
                'status' => $location?->zone === 'pre_ripening' ? 'pre_ripening' : 'receiving',
                'storage_location_id' => $location?->id,
                'received_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            Inspection::create([
                'batch_id' => $batch->id,
                'user_id' => $request->user()->id,
                'type' => 'arrival',
                'stage' => $batch->stage,
                'grade' => $batch->grade,
                'damage_pct' => $data['damage_pct'] ?? 0,
                'pulp_temp' => $data['pulp_temp'] ?? null,
                'notes' => 'Arrival log',
            ]);

            $batch->movements()->create([
                'to_location_id' => $location?->id,
                'to_status' => $batch->status,
                'quantity_kg' => $batch->initial_kg,
                'user_id' => $request->user()->id,
                'note' => 'Received from supplier',
            ]);

            ActivityLog::record('batch.received', "Received {$batch->code}: ".number_format($batch->initial_kg).' kg', $batch);

            return $batch;
        });

        return response()->json($batch->load('supplier', 'location'), 201);
    }

    public function updateStage(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(Batch::STAGES)],
            'notes' => 'nullable|string|max:500',
        ]);

        $old = $batch->stage;
        $batch->stage = $data['stage'];
        if ($data['stage'] === 'ripe' && ! $batch->ripe_at) {
            $batch->ripe_at = now();
        }
        $batch->save();

        Inspection::create([
            'batch_id' => $batch->id, 'user_id' => $request->user()->id, 'type' => 'ripening',
            'stage' => $batch->stage, 'grade' => $batch->grade, 'notes' => $data['notes'] ?? "Stage updated from $old",
        ]);
        ActivityLog::record('batch.stage', "{$batch->code} stage {$old} → {$batch->stage}", $batch);

        return $batch->fresh(['supplier', 'location']);
    }

    public function move(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'storage_location_id' => 'required|exists:storage_locations,id',
            'ripening_days' => 'nullable|numeric|min:1|max:10',
            'note' => 'nullable|string|max:255',
        ]);
        $to = StorageLocation::findOrFail($data['storage_location_id']);
        $status = match ($to->zone) {
            'pre_ripening' => 'pre_ripening',
            'ripening' => 'ripening',
            'sales', 'dispatch' => 'sales',
            default => 'receiving',
        };

        if ($status === 'sales' && ! in_array($batch->stage, ['ripe', 'overripe'])) {
            return response()->json(['message' => 'Only ripe bananas can be moved to sales inventory.'], 422);
        }
        if ($to->status === 'maintenance') {
            return response()->json(['message' => "{$to->name} is under maintenance."], 422);
        }

        if ($status === 'ripening' && $batch->status !== 'ripening') {
            $batch->ripening_started_at = now();
            $batch->expected_ripe_at = now()->addHours((int) (($data['ripening_days'] ?? 5) * 24));
        }

        $from = $batch->location?->name ?? 'Unassigned';
        Warehouse::move($batch, $status, $to, $data['note'] ?? null);
        ActivityLog::record('batch.moved', "{$batch->code} moved {$from} → {$to->name}", $batch);

        return $batch->fresh(['supplier', 'location']);
    }

    public function loss(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'quantity_kg' => 'required|integer|min:1|max:'.$batch->remaining_kg,
            'reason' => 'required|in:spoiled,damaged,overripe,handling,other',
            'notes' => 'nullable|string|max:500',
        ]);
        Warehouse::recordLoss($batch, $data['quantity_kg'], $data['reason'], $data['notes'] ?? null);
        ActivityLog::record('batch.loss', "Logged {$data['quantity_kg']} kg {$data['reason']} on {$batch->code}", $batch);

        return $batch->fresh(['supplier', 'location']);
    }

    public function inspect(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'type' => 'required|in:arrival,ripening,pre_sale',
            'stage' => ['required', Rule::in(Batch::STAGES)],
            'grade' => 'required|in:A,B,C',
            'damage_pct' => 'nullable|numeric|min:0|max:100',
            'pulp_temp' => 'nullable|numeric',
            'checks' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'storage_location_id' => 'nullable|exists:storage_locations,id',
        ]);

        $inspection = $batch->inspections()->create(collect($data)->except('storage_location_id')->all() + ['user_id' => $request->user()->id]);
        $batch->update(['grade' => $data['grade'], 'stage' => $data['stage']] + ($data['stage'] === 'ripe' && ! $batch->ripe_at ? ['ripe_at' => now()] : []));

        if ($batch->status === 'receiving' && ! empty($data['storage_location_id'])) {
            Warehouse::move($batch, 'pre_ripening', StorageLocation::find($data['storage_location_id']), 'Passed inspection');
        }
        ActivityLog::record('batch.inspected', "Inspected {$batch->code}: grade {$data['grade']}, {$data['stage']}", $batch);

        return $inspection->load('user:id,name');
    }
}
