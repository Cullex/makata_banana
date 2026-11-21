<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\BatchMovement;
use App\Models\Customer;
use App\Models\Inspection;
use App\Models\Loss;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceRule;
use App\Models\RoomReading;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic demo by simulating 45 days of warehouse operations:
 * arrivals → inspection → pre-ripening → ripening rooms → sales (FIFO) → spoilage.
 * Every number on the dashboard is therefore internally consistent.
 */
class DatabaseSeeder extends Seeder
{
    private array $logs = [];

    public function run(): void
    {
        mt_srand(2026);
        $now = now();

        // ── People ────────────────────────────────────────────────────────────
        $users = collect([
            ['Amina Otieno', 'manager@makata.test', 'manager'],
            ['Brian Kamau', 'warehouse@makata.test', 'warehouse'],
            ['Faith Mutua', 'inspector@makata.test', 'inspector'],
            ['Kevin Njoroge', 'sales@makata.test', 'sales'],
            ['Peter Ochieng', 'peter@makata.test', 'warehouse'],
            ['Lucy Achieng', 'lucy@makata.test', 'sales'],
            ['Daniel Kiprop', 'daniel@makata.test', 'inspector'],
        ])->map(fn ($u) => User::create([
            'name' => $u[0], 'email' => $u[1], 'role' => $u[2], 'password' => 'password',
            'phone' => '+254 7'.mt_rand(10, 99).' '.mt_rand(100, 999).' '.mt_rand(100, 999),
            'last_seen_at' => $now->copy()->subMinutes(mt_rand(1, 600)),
        ]));
        $byRole = fn ($r) => $users->where('role', $r)->values();
        $pick = fn ($r) => $byRole($r)[mt_rand(0, $byRole($r)->count() - 1)];

        $suppliers = collect([
            ['Meru Highlands Growers', 'Joseph Mwiti', 'Meru', 4.8, 0.95],
            ['Kisii Valley Farms', 'Grace Moraa', 'Kisii', 4.5, 1.0],
            ['Taveta Banana Co-op', 'Hassan Mwakio', 'Taita Taveta', 4.2, 1.08],
            ["Murang'a Smallholders", 'Esther Wairimu', "Murang'a", 4.0, 1.04],
            ['Embu Fresh Produce', 'Samuel Njiru', 'Embu', 4.6, 0.98],
            ['Kirinyaga Agro Ltd', 'Ruth Wangari', 'Kirinyaga', 3.8, 1.12],
        ])->map(fn ($s) => tap(Supplier::create([
            'name' => $s[0], 'contact_person' => $s[1], 'region' => $s[2], 'rating' => $s[3],
            'phone' => '+254 7'.mt_rand(10, 99).' '.mt_rand(100, 999).' '.mt_rand(100, 999),
        ]), fn ($m) => $m->ripen_factor = $s[4]));

        $customers = collect([
            ['Greenmart Supermarket', 'supermarket', 'Nairobi CBD'],
            ['City Fresh Grocers', 'retailer', 'Westlands'],
            ['Lakeview Hotel', 'hotel', 'Naivasha'],
            ["Mama Njeri's Stall", 'market', 'Wakulima Market'],
            ['Sunrise Wholesalers', 'wholesaler', 'Industrial Area'],
            ['Highway Fruit Traders', 'wholesaler', 'Thika Road'],
            ['Bluebell Schools Catering', 'hotel', 'Karen'],
            ['Juicy Bar & Smoothies', 'retailer', 'Kilimani'],
            ['Riverside Mini-Mart', 'supermarket', 'Riverside'],
            ['Kilele Hotels Group', 'hotel', 'Upper Hill'],
            ['Gikomba Fruit Sellers', 'market', 'Gikomba'],
        ])->map(fn ($c) => Customer::create([
            'name' => $c[0], 'type' => $c[1], 'location' => $c[2],
            'phone' => '+254 7'.mt_rand(10, 99).' '.mt_rand(100, 999).' '.mt_rand(100, 999),
        ]));

        // ── Physical layout ──────────────────────────────────────────────────
        $loc = fn ($code, $name, $zone, $cap, $status, $t, $h, $e) => StorageLocation::create([
            'code' => $code, 'name' => $name, 'zone' => $zone, 'capacity_kg' => $cap, 'status' => $status,
            'target_temp' => $t, 'target_humidity' => $h, 'target_ethylene' => $e,
        ]);
        $receiving = $loc('RCV-01', 'Receiving Bay', 'receiving', 15000, 'active', 18, 80, 0);
        $pre = collect([
            $loc('PRE-A', 'Pre-Ripening Store A', 'pre_ripening', 24000, 'active', 13.5, 88, 0),
            $loc('PRE-B', 'Pre-Ripening Store B', 'pre_ripening', 24000, 'active', 13.5, 88, 0),
        ]);
        $rooms = collect([
            $loc('RR-01', 'Ripening Room 1', 'ripening', 12000, 'gassing', 17.5, 93, 120),
            $loc('RR-02', 'Ripening Room 2', 'ripening', 12000, 'active', 16.5, 92, 0),
            $loc('RR-03', 'Ripening Room 3', 'ripening', 12000, 'gassing', 18.0, 94, 150),
            $loc('RR-04', 'Ripening Room 4', 'ripening', 12000, 'venting', 15.5, 91, 0),
            $loc('RR-05', 'Ripening Room 5', 'ripening', 12000, 'active', 16.0, 92, 0),
            $loc('RR-06', 'Ripening Room 6', 'ripening', 12000, 'maintenance', 14.0, 90, 0),
        ]);
        $activeRooms = $rooms->take(5)->values();
        $sales = collect([
            $loc('SAL-01', 'Sales Cold Store', 'sales', 30000, 'active', 13.5, 90, 0),
            $loc('SAL-02', 'Ready Stock Bay', 'sales', 16000, 'active', 14.0, 88, 0),
        ]);
        $loc('DSP-01', 'Dispatch & Loading Dock', 'dispatch', 8000, 'active', 16, 80, 0);

        // ── Pricing (per kg) ─────────────────────────────────────────────────
        $prices = [];
        foreach (['A' => 0.95, 'B' => 0.82, 'C' => 0.68] as $grade => $p) {
            $prices["ripe.$grade"] = PriceRule::create(['stage' => 'ripe', 'grade' => $grade, 'price_per_kg' => $p, 'discount_pct' => 0]);
            $prices["overripe.$grade"] = PriceRule::create(['stage' => 'overripe', 'grade' => $grade, 'price_per_kg' => $p, 'discount_pct' => ['A' => 35, 'B' => 40, 'C' => 45][$grade]]);
        }

        // ── Simulation ───────────────────────────────────────────────────────
        $start = today()->subDays(45);
        $batches = [];
        $roomBookings = []; // [roomIndex => [[start,end],...]]
        $orderSeq = [];

        for ($d = 0; $d <= 45; $d++) {
            $day = $start->copy()->addDays($d);
            $isToday = $day->isToday();

            // Arrivals
            $n = $this->weighted([0 => 12, 1 => 58, 2 => 30]);
            if ($isToday) {
                $n = 1;
            }
            for ($i = 0; $i < $n; $i++) {
                $arrived = $day->copy()->setTime(mt_rand(6, 10), mt_rand(0, 59));
                if ($arrived->gt($now)) {
                    $arrived = $now->copy()->subHours(mt_rand(6, 9));
                }
                $batches[] = $this->planBatch($arrived, $suppliers, $activeRooms, $roomBookings, count($batches) + 1);
            }

            // Orders throughout the day
            $orders = mt_rand(6, 11);
            $times = collect(range(1, $orders))->map(fn () => $day->copy()->setTime(mt_rand(7, 17), mt_rand(0, 59)))->sort()->values();
            foreach ($times as $t) {
                if ($t->gt($now)) {
                    continue;
                }
                $customer = $customers->random();
                $want = (int) (round(mt_rand(15, 110) * ($customer->type === 'wholesaler' ? 1.6 : 1)) * 10);
                $this->sell($t, $customer, $want, $batches, $prices, $pick, $orderSeq, $now);
            }

            // End of day: spoilage + random handling damage
            $eod = $day->copy()->setTime(20, 0);
            foreach ($batches as &$b) {
                if ($b['remaining'] <= 0 || $b['spoiled']) {
                    continue;
                }
                if ($b['sales_at']->lte($eod) && $b['ripe_actual']->copy()->addDays(Batch::RIPE_SHELF_DAYS + Batch::OVERRIPE_SHELF_DAYS)->lte($eod) && $eod->lte($now)) {
                    $b['losses'][] = ['kg' => $b['remaining'], 'reason' => 'spoiled', 'at' => $eod->copy(), 'note' => 'Past shelf life, sent to compost'];
                    $b['remaining'] = 0;
                    $b['spoiled'] = true;
                    $b['depleted_at'] = $eod->copy();
                } elseif ($b['arrived']->lte($eod) && $eod->lte($now) && mt_rand(1, 100) <= 4) {
                    $kg = min($b['remaining'], mt_rand(2, 8) * 18);
                    $reason = ['damaged', 'handling', 'overripe'][mt_rand(0, 2)];
                    $b['losses'][] = ['kg' => $kg, 'reason' => $reason, 'at' => $eod->copy()->subHours(mt_rand(1, 8)), 'note' => ['damaged' => 'Crushed cartons found during stacking', 'handling' => 'Dropped pallet at loading', 'overripe' => 'Split peels, removed from stock'][$reason]];
                    $b['remaining'] -= $kg;
                }
            }
            unset($b);
        }

        // Two fresh trucks waiting at receiving for inspection
        foreach ([95, 35] as $minsAgo) {
            $b = $this->planBatch($now->copy()->subMinutes($minsAgo), $suppliers, $activeRooms, $roomBookings, count($batches) + 1);
            $b['awaiting_inspection'] = true;
            $batches[] = $b;
        }

        $this->persist($batches, $receiving, $pre, $activeRooms, $sales, $pick, $now);
        $this->persistOrders($pick, $batches);
        $this->readings($rooms->merge($pre)->merge($sales)->push($receiving), $now);

        ActivityLog::insert(collect($this->logs)->sortBy('created_at')->values()->all());

        $this->demoScenario($now);
    }

    /** Guarantee the demo always shows the key alert types. */
    private function demoScenario(Carbon $now): void
    {
        // A batch that has just turned ripe inside its ripening room
        if (! Batch::where('status', 'ripening')->where('stage', 'ripe')->exists()) {
            $b = Batch::where('status', 'ripening')->orderBy('ripening_started_at')->first();
            $b?->update(['stage' => 'ripe', 'ripe_at' => $now->copy()->subHours(3), 'expected_ripe_at' => $now->copy()->subHours(5)]);
        }

        // An older sales batch close to the end of its shelf life
        $sales = Batch::where('status', 'sales')->where('remaining_kg', '>', 0)->orderBy('ripe_at')->get();
        if ($sales->count() > 1 && ! $sales->contains(fn ($b) => $b->shelf_days_left !== null && $b->shelf_days_left <= 2)) {
            $sales->first()->update([
                'stage' => 'overripe',
                'ripe_at' => $now->copy()->subDays(4)->subHours(14),
                'remaining_kg' => min($sales->first()->remaining_kg, 1260),
            ]);
        }
    }

    private function weighted(array $w): int
    {
        $r = mt_rand(1, array_sum($w));
        foreach ($w as $k => $v) {
            if (($r -= $v) <= 0) {
                return $k;
            }
        }

        return array_key_first($w);
    }

    private function planBatch(Carbon $arrived, $suppliers, $rooms, array &$bookings, int $n): array
    {
        $supplier = $suppliers[$this->weighted([0 => 22, 1 => 20, 2 => 14, 3 => 16, 4 => 18, 5 => 10])];
        $kg = mt_rand(140, 380) * 18;
        $grade = ['A', 'B', 'C'][$this->weighted([0 => 62, 1 => 29, 2 => 9])];
        $rStart = $arrived->copy()->addHours(mt_rand(20, 46));
        $expectedHours = mt_rand(108, 132);
        $actualHours = (int) round($expectedHours * $supplier->ripen_factor + mt_rand(-10, 14));

        // Pick the least-busy ripening room at that moment
        $roomIdx = collect(range(0, $rooms->count() - 1))->sortBy(fn ($i) => collect($bookings[$i] ?? [])
            ->filter(fn ($bk) => $bk[0]->lte($rStart) && $bk[1]->gte($rStart))->count() * 100 + mt_rand(0, 9))->first();
        $salesAt = $rStart->copy()->addHours($actualHours + mt_rand(2, 10));
        $bookings[$roomIdx][] = [$rStart, $salesAt];

        return [
            'n' => $n, 'supplier' => $supplier, 'kg' => $kg, 'remaining' => $kg, 'grade' => $grade,
            'variety' => mt_rand(1, 100) <= 82 ? 'Cavendish' : ['Williams', 'Grand Nain', 'Lady Finger'][mt_rand(0, 2)],
            'stage0' => mt_rand(1, 100) <= 90 ? 'green' : 'turning',
            'cost' => mt_rand(42, 58) / 100 - ($grade === 'C' ? 0.06 : 0),
            'damage' => mt_rand(0, 40) / 10,
            'pulp' => mt_rand(130, 160) / 10,
            'arrived' => $arrived, 'inspected_at' => $arrived->copy()->addMinutes(mt_rand(30, 90)),
            'ripen_start' => $rStart, 'expected_ripe' => $rStart->copy()->addHours($expectedHours),
            'ripe_actual' => $rStart->copy()->addHours($actualHours), 'sales_at' => $salesAt,
            'room' => $roomIdx, 'sales_room' => mt_rand(0, 3) === 0 ? 1 : 0,
            'sold' => [], 'losses' => [], 'spoiled' => false, 'depleted_at' => null, 'awaiting_inspection' => false,
        ];
    }

    private array $orders = [];

    private function sell(Carbon $t, Customer $customer, int $want, array &$batches, array $prices, $pick, array &$seq, Carbon $now): void
    {
        $avail = collect($batches)->keys()->filter(fn ($k) => $batches[$k]['remaining'] > 0 && ! $batches[$k]['spoiled'] && $batches[$k]['sales_at']->lte($t))
            ->sortBy(fn ($k) => $batches[$k]['ripe_actual']->timestamp)->values();
        if ($avail->isEmpty()) {
            return;
        }
        // Safety stock: over the last 3 days keep ~12 t of saleable fruit on hand
        // so the demo always opens with ripe stock to sell.
        if ($t->gte($now->copy()->subDays(3))) {
            $onHand = $avail->sum(fn ($k) => $batches[$k]['remaining']);
            $want = min($want, max(0, $onHand - 12000));
            if ($want < 50) {
                return;
            }
        }
        $cancelled = mt_rand(1, 100) <= 2;
        $items = [];
        foreach ($avail as $k) {
            if ($want <= 0) {
                break;
            }
            $b = &$batches[$k];
            $qty = min($b['remaining'], $want);
            $want -= $qty;
            $overripe = $t->gte($b['ripe_actual']->copy()->addDays(Batch::RIPE_SHELF_DAYS));
            $stage = $overripe ? 'overripe' : 'ripe';
            $rule = $prices["$stage.{$b['grade']}"];
            $disc = $rule->discount_pct;
            if ($t->diffInHours($b['ripe_actual']->copy()->addDays(6), false) < 24) {
                $disc = max($disc, 50);
            }
            if (! $cancelled) {
                $b['remaining'] -= $qty;
                if ($b['remaining'] === 0) {
                    $b['depleted_at'] = $t->copy();
                }
            }
            $items[] = ['batch_key' => $k, 'stage' => $stage, 'qty' => $qty, 'price' => $rule->price_per_kg, 'disc' => $disc];
            unset($b);
        }

        $key = $t->format('ymd');
        $seq[$key] = ($seq[$key] ?? 0) + 1;
        $age = $t->diffInHours($now);
        $status = $cancelled ? 'cancelled' : ($age > 48 ? 'delivered' : ($age > 20 ? ['dispatched', 'delivered'][mt_rand(0, 1)] : ['allocated', 'packed', 'dispatched'][mt_rand(0, 2)]));
        $this->orders[] = ['number' => "SO-$key-".str_pad($seq[$key], 3, '0', STR_PAD_LEFT), 'customer' => $customer, 'at' => $t->copy(), 'status' => $status, 'items' => $items];
    }

    private function persist(array &$batches, $receiving, $pre, $rooms, $sales, $pick, Carbon $now): void
    {
        foreach ($batches as $k => &$b) {
            $code = 'MB-'.$b['arrived']->format('ymd').'-'.str_pad($b['n'], 3, '0', STR_PAD_LEFT);
            $room = $rooms[$b['room']];
            $receiver = $pick('warehouse');

            // Determine the state as of "now"
            if ($b['awaiting_inspection']) {
                [$status, $stage, $location] = ['receiving', $b['stage0'], $receiving];
            } elseif ($b['spoiled']) {
                [$status, $stage, $location] = ['spoiled', 'overripe', null];
            } elseif ($b['remaining'] <= 0) {
                [$status, $stage, $location] = ['depleted', 'ripe', null];
            } elseif ($now->lt($b['ripen_start'])) {
                [$status, $stage, $location] = ['pre_ripening', $b['stage0'], $pre[$b['n'] % 2]];
            } elseif ($now->lt($b['sales_at'])) {
                $p = $b['ripen_start']->diffInMinutes($now) / max(1, $b['ripen_start']->diffInMinutes($b['ripe_actual']));
                [$status, $stage, $location] = ['ripening', $now->gte($b['ripe_actual']) ? 'ripe' : ($p < 0.45 ? 'green' : 'turning'), $room];
            } else {
                $over = $now->gte($b['ripe_actual']->copy()->addDays(Batch::RIPE_SHELF_DAYS));
                [$status, $stage, $location] = ['sales', $over ? 'overripe' : 'ripe', $sales[$b['sales_room']]];
            }
            $started = ! $b['awaiting_inspection'] && $now->gte($b['ripen_start']);
            $isRipe = $started && $now->gte($b['ripe_actual']);

            $batch = Batch::create([
                'code' => $code, 'supplier_id' => $b['supplier']->id, 'storage_location_id' => $location?->id,
                'received_by' => $receiver->id, 'variety' => $b['variety'], 'initial_kg' => $b['kg'],
                'remaining_kg' => $b['remaining'], 'cartons' => intdiv($b['kg'], 18), 'stage' => $stage, 'status' => $status,
                'grade' => $b['grade'], 'cost_per_kg' => $b['cost'], 'arrived_at' => $b['arrived'],
                'ripening_started_at' => $started ? $b['ripen_start'] : null,
                'expected_ripe_at' => $started || $status === 'pre_ripening' ? $b['expected_ripe'] : null,
                'ripe_at' => $isRipe ? $b['ripe_actual'] : null,
                'depleted_at' => in_array($status, ['depleted', 'spoiled']) ? $b['depleted_at'] : null,
                'notes' => mt_rand(1, 5) === 1 ? 'Truck arrived with tarpaulin damage on 2 pallets.' : null,
                'created_at' => $b['arrived'], 'updated_at' => $now,
            ]);
            $b['id'] = $batch->id;
            $this->log($receiver, 'batch.received', "Received $code: ".number_format($b['kg']).' kg from '.$b['supplier']->name, $batch, $b['arrived']);

            $mv = fn ($from, $to, $fs, $ts, $at, $note, $user) => BatchMovement::create([
                'batch_id' => $batch->id, 'from_location_id' => $from?->id, 'to_location_id' => $to?->id,
                'from_status' => $fs, 'to_status' => $ts, 'quantity_kg' => $b['kg'], 'user_id' => $user->id,
                'note' => $note, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $mv(null, $receiving, null, 'receiving', $b['arrived'], 'Unloaded from truck', $receiver);

            if ($b['awaiting_inspection']) {
                continue;
            }

            $inspector = $pick('inspector');
            Inspection::create([
                'batch_id' => $batch->id, 'user_id' => $inspector->id, 'type' => 'arrival', 'stage' => $b['stage0'], 'grade' => $b['grade'],
                'damage_pct' => $b['damage'], 'pulp_temp' => $b['pulp'],
                'checks' => ['crown_rot' => $b['damage'] < 3, 'latex_stains' => $b['damage'] < 2, 'bruising' => $b['damage'] < 2.5, 'size_uniform' => $b['grade'] !== 'C'],
                'notes' => $b['damage'] > 3 ? 'Minor bruising on outer hands, acceptable.' : 'Good fruit, uniform fingers.',
                'created_at' => $b['inspected_at'], 'updated_at' => $b['inspected_at'],
            ]);
            $this->log($inspector, 'batch.inspected', "Inspected $code: grade {$b['grade']}, {$b['stage0']}", $batch, $b['inspected_at']);
            $preLoc = $pre[$b['n'] % 2];
            $mv($receiving, $preLoc, 'receiving', 'pre_ripening', $b['inspected_at'], 'Passed inspection', $receiver);
            $this->log($receiver, 'batch.moved', "$code moved Receiving Bay → {$preLoc->name}", $batch, $b['inspected_at']);

            if (! $started) {
                continue;
            }
            $mover = $pick('warehouse');
            $mv($preLoc, $room, 'pre_ripening', 'ripening', $b['ripen_start'], 'Scheduled ripening cycle', $mover);
            $this->log($mover, 'batch.moved', "$code moved {$preLoc->name} → {$room->name}", $batch, $b['ripen_start']);

            $mid = $b['ripen_start']->copy()->addHours((int) ($b['ripen_start']->diffInHours($b['ripe_actual']) * 0.55));
            if ($mid->lt($now)) {
                Inspection::create([
                    'batch_id' => $batch->id, 'user_id' => $inspector->id, 'type' => 'ripening', 'stage' => 'turning', 'grade' => $b['grade'],
                    'pulp_temp' => mt_rand(165, 185) / 10, 'notes' => 'Colour stage 3: more green than yellow.', 'created_at' => $mid, 'updated_at' => $mid,
                ]);
                $this->log($inspector, 'batch.stage', "$code stage green → turning", $batch, $mid);
            }
            if ($isRipe) {
                Inspection::create([
                    'batch_id' => $batch->id, 'user_id' => $inspector->id, 'type' => 'pre_sale', 'stage' => 'ripe', 'grade' => $b['grade'],
                    'pulp_temp' => mt_rand(140, 160) / 10, 'notes' => 'Colour stage 5: yellow with green tips. Ready for sale.', 'created_at' => $b['ripe_actual'], 'updated_at' => $b['ripe_actual'],
                ]);
                $this->log($inspector, 'batch.stage', "$code stage turning → ripe", $batch, $b['ripe_actual']);
            }
            if ($now->gte($b['sales_at'])) {
                $s = $sales[$b['sales_room']];
                $mv($room, $s, 'ripening', 'sales', $b['sales_at'], 'Ripe, moved to sales inventory', $mover);
                $this->log($mover, 'batch.moved', "$code moved {$room->name} → {$s->name}", $batch, $b['sales_at']);
            }
            foreach ($b['losses'] as $l) {
                $u = $pick('warehouse');
                Loss::create(['batch_id' => $batch->id, 'user_id' => $u->id, 'quantity_kg' => $l['kg'], 'reason' => $l['reason'], 'notes' => $l['note'], 'created_at' => $l['at'], 'updated_at' => $l['at']]);
                $this->log($u, 'batch.loss', "Logged {$l['kg']} kg {$l['reason']} on $code", $batch, $l['at']);
            }
        }
        unset($b);
    }

    private function persistOrders($pick, array $batches): void
    {
        foreach ($this->orders as $o) {
            $lines = collect($o['items'])->map(fn ($i) => $i + [
                'gross' => $i['qty'] * $i['price'],
                'total' => round($i['qty'] * $i['price'] * (1 - $i['disc'] / 100), 2),
            ]);
            $user = $pick('sales');
            $order = Order::create([
                'number' => $o['number'], 'customer_id' => $o['customer']->id, 'user_id' => $user->id, 'status' => $o['status'],
                'quantity_kg' => $lines->sum('qty'), 'subtotal' => $lines->sum('gross'),
                'discount' => round($lines->sum('gross') - $lines->sum('total'), 2), 'total' => $lines->sum('total'),
                'ordered_at' => $o['at'], 'created_at' => $o['at'], 'updated_at' => $o['at'],
            ]);
            foreach ($lines as $l) {
                OrderItem::create([
                    'order_id' => $order->id, 'batch_id' => $batches[$l['batch_key']]['id'], 'stage' => $l['stage'],
                    'quantity_kg' => $l['qty'], 'unit_price' => $l['price'], 'discount_pct' => $l['disc'], 'line_total' => $l['total'],
                    'created_at' => $o['at'], 'updated_at' => $o['at'],
                ]);
            }
            $this->log($user, 'order.created', "Created order {$order->number} for {$o['customer']->name}: ".number_format($order->quantity_kg).' kg', $order, $o['at']);
        }
    }

    private function readings($locations, Carbon $now): void
    {
        $rows = [];
        foreach ($locations as $l) {
            for ($h = 72; $h >= 1; $h--) {
                $t = $now->copy()->subHours($h);
                $gas = $l->status === 'gassing' && $h < 30;
                $rows[] = [
                    'storage_location_id' => $l->id,
                    'temperature' => round($l->target_temp + sin($h / 4 + $l->id) * 0.5 + mt_rand(-3, 3) / 10, 1),
                    'humidity' => round(min(99, $l->target_humidity + sin($h / 6 + $l->id) * 1.5 + mt_rand(-5, 5) / 10), 1),
                    'ethylene' => $gas ? (int) max(0, $l->target_ethylene + sin($h / 3) * 12 + mt_rand(-5, 5)) : mt_rand(0, 4),
                    'recorded_at' => $t,
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            RoomReading::insert($chunk);
        }
    }

    private function log(User $user, string $action, string $desc, $subject, Carbon $at): void
    {
        $this->logs[] = [
            'user_id' => $user->id, 'action' => $action, 'description' => $desc,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'meta' => null, 'created_at' => $at->copy(),
        ];
    }
}
