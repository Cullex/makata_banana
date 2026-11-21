<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = Order::with('customer:id,name,type', 'user:id,name')->withCount('items');
        if ($s = $request->query('search')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $counts = Order::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return response()->json(array_merge(
            $q->latest('ordered_at')->paginate((int) $request->query('per_page', 15))->toArray(),
            ['counts' => $counts]
        ));
    }

    public function show(Order $order)
    {
        return $order->load('customer', 'user:id,name', 'items.batch:id,code,supplier_id,grade', 'items.batch.supplier:id,name');
    }

    protected function rules(): array
    {
        return [
            'lines' => 'required|array|min:1',
            'lines.*.stage' => 'required|in:ripe,overripe',
            'lines.*.grade' => 'nullable|in:A,B,C',
            'lines.*.quantity_kg' => 'required|integer|min:1',
        ];
    }

    public function preview(Request $request)
    {
        $data = $request->validate($this->rules());

        return Warehouse::allocate($data['lines']);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'customer_id' => 'required|exists:customers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $plan = Warehouse::allocate($data['lines']);
            $order = Order::create([
                'number' => 'SO-'.now()->format('ymd').'-'.str_pad(Order::whereDate('ordered_at', today())->count() + 1, 3, '0', STR_PAD_LEFT),
                'customer_id' => $data['customer_id'],
                'user_id' => $request->user()->id,
                'status' => 'allocated',
                'quantity_kg' => $plan['quantity_kg'],
                'subtotal' => $plan['subtotal'],
                'discount' => $plan['discount'],
                'total' => $plan['total'],
                'notes' => $data['notes'] ?? null,
                'ordered_at' => now(),
            ]);
            foreach ($plan['allocations'] as $a) {
                $item = $order->items()->create([
                    'batch_id' => $a['batch_id'], 'stage' => $a['stage'], 'quantity_kg' => $a['quantity_kg'],
                    'unit_price' => $a['unit_price'], 'discount_pct' => $a['discount_pct'], 'line_total' => $a['line_total'],
                ]);
                Warehouse::deduct($item);
            }
            ActivityLog::record('order.created', "Created order {$order->number}: ".number_format($order->quantity_kg).' kg', $order);

            return $order;
        });

        return response()->json($this->show($order), 201);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => 'required|in:allocated,packed,dispatched,delivered,cancelled']);
        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Cancelled orders cannot be changed.'], 422);
        }

        DB::transaction(function () use ($order, $data) {
            if ($data['status'] === 'cancelled') {
                foreach ($order->items()->with('batch')->get() as $item) {
                    $b = $item->batch;
                    $b->remaining_kg += $item->quantity_kg;
                    if ($b->status === 'depleted') {
                        $b->status = 'sales';
                        $b->depleted_at = null;
                    }
                    $b->save();
                }
            }
            $order->update(['status' => $data['status']]);
            ActivityLog::record('order.status', "Order {$order->number} marked {$data['status']}", $order);
        });

        return $this->show($order);
    }
}
