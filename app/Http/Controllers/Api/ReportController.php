<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Loss;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $days = (int) $request->query('days', 30);
        $from = today()->subDays($days - 1);
        $period = $request->query('period', 'daily'); // daily | weekly | monthly

        $fmt = match ($period) {
            'weekly' => "DATE_FORMAT(ordered_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(ordered_at, '%Y-%m')",
            default => 'DATE(ordered_at)',
        };

        $sales = Order::where('status', '!=', 'cancelled')->where('ordered_at', '>=', $from)
            ->selectRaw("$fmt as label, SUM(total) revenue, SUM(quantity_kg) kg, COUNT(*) orders, SUM(discount) discount")
            ->groupBy('label')->orderBy('label')->get();

        $byStage = OrderItem::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->where('ordered_at', '>=', $from))
            ->selectRaw('stage, SUM(quantity_kg) kg, SUM(line_total) revenue')->groupBy('stage')->get();

        $bySupplier = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('batches', 'batches.id', '=', 'order_items.batch_id')
            ->join('suppliers', 'suppliers.id', '=', 'batches.supplier_id')
            ->where('orders.status', '!=', 'cancelled')->where('orders.ordered_at', '>=', $from)
            ->selectRaw('suppliers.id, suppliers.name, SUM(order_items.quantity_kg) kg, SUM(order_items.line_total) revenue')
            ->groupBy('suppliers.id', 'suppliers.name')->orderByDesc('revenue')->get();

        $ripened = Batch::whereNotNull('ripe_at')->whereNotNull('ripening_started_at')->where('ripe_at', '>=', $from)->with('supplier:id,name')->get();
        $ripeningBySupplier = $ripened->groupBy('supplier.name')->map(fn ($g, $name) => [
            'name' => $name,
            'avg_days' => round($g->avg(fn ($b) => $b->ripening_started_at->diffInHours($b->ripe_at) / 24), 2),
            'batches' => $g->count(),
        ])->values();
        $ripeningDist = $ripened->groupBy(fn ($b) => (string) round($b->ripening_started_at->diffInHours($b->ripe_at) / 24))
            ->map(fn ($g, $d) => ['days' => (int) $d, 'batches' => $g->count()])->sortBy('days')->values();

        $receivedBySupplier = Batch::where('arrived_at', '>=', $from)->selectRaw('supplier_id, SUM(initial_kg) kg')->groupBy('supplier_id')->pluck('kg', 'supplier_id');
        $lossBySupplier = Loss::join('batches', 'batches.id', '=', 'losses.batch_id')->where('losses.created_at', '>=', $from)
            ->selectRaw('batches.supplier_id, SUM(losses.quantity_kg) kg')->groupBy('batches.supplier_id')->pluck('kg', 'supplier_id');

        $suppliers = Supplier::all()->map(fn ($s) => [
            'name' => $s->name,
            'received_kg' => (int) ($receivedBySupplier[$s->id] ?? 0),
            'lost_kg' => (int) ($lossBySupplier[$s->id] ?? 0),
            'spoilage_rate' => ($receivedBySupplier[$s->id] ?? 0) ? round(($lossBySupplier[$s->id] ?? 0) / $receivedBySupplier[$s->id] * 100, 2) : 0,
            'avg_ripening' => $ripeningBySupplier->firstWhere('name', $s->name)['avg_days'] ?? null,
            'revenue' => (float) ($bySupplier->firstWhere('id', $s->id)->revenue ?? 0),
        ])->sortByDesc('revenue')->values();

        $weeklyLoss = Loss::where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%x-W%v') label, SUM(quantity_kg) kg")->groupBy('label')->orderBy('label')->pluck('kg', 'label');
        $weeklyIn = Batch::where('arrived_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(arrived_at, '%x-W%v') label, SUM(initial_kg) kg")->groupBy('label')->orderBy('label')->pluck('kg', 'label');
        $spoilage = $weeklyIn->map(fn ($in, $label) => [
            'label' => $label, 'received' => (int) $in, 'lost' => (int) ($weeklyLoss[$label] ?? 0),
            'rate' => $in ? round(($weeklyLoss[$label] ?? 0) / $in * 100, 2) : 0,
        ])->values();

        $customers = Order::where('status', '!=', 'cancelled')->where('ordered_at', '>=', $from)
            ->select('customer_id', DB::raw('SUM(total) revenue'), DB::raw('SUM(quantity_kg) kg'), DB::raw('COUNT(*) orders'), DB::raw('MAX(ordered_at) last_order'))
            ->groupBy('customer_id')->orderByDesc('revenue')->with('customer:id,name,type,location')->get();

        $stock = Batch::inStock()->get();
        $expected = Batch::where('status', 'ripening')->where('stage', '!=', 'ripe')->orderBy('expected_ripe_at')->with('location:id,name', 'supplier:id,name')->get();

        $totalRevenue = $sales->sum('revenue');
        $totalKg = $sales->sum('kg');

        return [
            'range' => ['from' => $from->toDateString(), 'to' => today()->toDateString(), 'days' => $days, 'period' => $period],
            'summary' => [
                'revenue' => round($totalRevenue, 2),
                'kg' => (int) $totalKg,
                'orders' => (int) $sales->sum('orders'),
                'avg_price' => $totalKg ? round($totalRevenue / $totalKg, 2) : 0,
                'discounts' => round($sales->sum('discount'), 2),
                'avg_ripening_days' => round($ripened->avg(fn ($b) => $b->ripening_started_at->diffInHours($b->ripe_at) / 24) ?? 0, 2),
                'spoilage_rate' => $weeklyIn->sum() ? round($weeklyLoss->sum() / $weeklyIn->sum() * 100, 2) : 0,
                'lost_kg' => (int) $weeklyLoss->sum(),
            ],
            'sales' => $sales,
            'by_stage' => $byStage,
            'suppliers' => $suppliers,
            'ripening_distribution' => $ripeningDist,
            'spoilage' => $spoilage,
            'customers' => $customers,
            'inventory' => collect(['receiving', 'pre_ripening', 'ripening', 'sales'])->map(fn ($s) => [
                'status' => $s,
                'kg' => (int) $stock->where('status', $s)->sum('remaining_kg'),
                'stages' => collect(Batch::STAGES)->mapWithKeys(fn ($st) => [$st => (int) $stock->where('status', $s)->where('stage', $st)->sum('remaining_kg')]),
            ]),
            'expected_ripening' => $expected,
        ];
    }
}
