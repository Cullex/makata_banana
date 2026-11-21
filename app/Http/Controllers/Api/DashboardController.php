<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Loss;
use App\Models\Order;
use App\Models\StorageLocation;
use App\Services\Warehouse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Warehouse::autoAge();

        $stock = Batch::inStock()->get();
        $byStage = collect(Batch::STAGES)->mapWithKeys(fn ($s) => [$s => (int) $stock->where('stage', $s)->sum('remaining_kg')]);
        $byStatus = collect(['receiving', 'pre_ripening', 'ripening', 'sales'])
            ->mapWithKeys(fn ($s) => [$s => [
                'kg' => (int) $stock->where('status', $s)->sum('remaining_kg'),
                'batches' => $stock->where('status', $s)->count(),
            ]]);

        $todayOrders = Order::where('status', '!=', 'cancelled')->where('ordered_at', '>=', today());
        $yesterdayOrders = Order::where('status', '!=', 'cancelled')->whereBetween('ordered_at', [today()->subDay(), today()]);

        $received30 = Batch::where('arrived_at', '>=', now()->subDays(30))->sum('initial_kg');
        $lost30 = Loss::where('created_at', '>=', now()->subDays(30))->sum('quantity_kg');

        $avgRipening = Batch::whereNotNull('ripe_at')->whereNotNull('ripening_started_at')
            ->where('ripe_at', '>=', now()->subDays(30))
            ->get()->avg(fn ($b) => $b->ripening_started_at->diffInHours($b->ripe_at) / 24);

        $trend = Order::where('status', '!=', 'cancelled')->where('ordered_at', '>=', today()->subDays(13))
            ->selectRaw('DATE(ordered_at) d, SUM(total) revenue, SUM(quantity_kg) kg, COUNT(*) orders')
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');
        $sales = collect(range(13, 0))->map(function ($i) use ($trend) {
            $d = today()->subDays($i)->toDateString();

            return ['date' => $d, 'revenue' => (float) ($trend[$d]->revenue ?? 0), 'kg' => (int) ($trend[$d]->kg ?? 0), 'orders' => (int) ($trend[$d]->orders ?? 0)];
        });

        $rooms = StorageLocation::where('zone', 'ripening')->withSum(['batches as load_kg' => fn ($q) => $q->inStock()], 'remaining_kg')
            ->withCount(['batches as batch_count' => fn ($q) => $q->inStock()])->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'status' => $r->status,
                'load_kg' => (int) $r->load_kg, 'capacity_kg' => $r->capacity_kg, 'batch_count' => $r->batch_count,
                'target_temp' => $r->target_temp, 'live' => $r->liveReading(),
            ]);

        $topCustomers = Order::where('status', '!=', 'cancelled')->where('ordered_at', '>=', now()->subDays(30))
            ->select('customer_id', DB::raw('SUM(total) revenue'), DB::raw('SUM(quantity_kg) kg'), DB::raw('COUNT(*) orders'))
            ->groupBy('customer_id')->orderByDesc('revenue')->with('customer:id,name,type')->limit(5)->get();

        return [
            'kpis' => [
                'stock_kg' => (int) $stock->sum('remaining_kg'),
                'stock_batches' => $stock->count(),
                'sellable_kg' => (int) $stock->where('status', 'sales')->sum('remaining_kg'),
                'revenue_today' => (float) (clone $todayOrders)->sum('total'),
                'revenue_yesterday' => (float) (clone $yesterdayOrders)->sum('total'),
                'orders_today' => (clone $todayOrders)->count(),
                'kg_sold_today' => (int) (clone $todayOrders)->sum('quantity_kg'),
                'received_today_kg' => (int) Batch::where('arrived_at', '>=', today())->sum('initial_kg'),
                'spoilage_rate' => $received30 ? round($lost30 / $received30 * 100, 2) : 0,
                'avg_ripening_days' => round($avgRipening ?? 0, 1),
            ],
            'by_stage' => $byStage,
            'by_status' => $byStatus,
            'sales' => $sales,
            'rooms' => $rooms,
            'alerts' => Warehouse::alerts()->take(6)->values(),
            'activity' => ActivityLog::with('user:id,name,role')->latest('created_at')->limit(8)->get(),
            'top_customers' => $topCustomers,
            'fifo_next' => Batch::where('status', 'sales')->where('remaining_kg', '>', 0)->fifo()->with('supplier:id,name')->limit(4)->get(),
        ];
    }
}
