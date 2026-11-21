<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function suppliers()
    {
        return Supplier::withCount('batches')->withSum('batches as total_kg', 'initial_kg')
            ->withMax('batches as last_delivery', 'arrived_at')->orderBy('name')->get();
    }

    public function storeSupplier(Request $request)
    {
        $s = Supplier::create($request->validate([
            'name' => 'required|string|max:100', 'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30', 'region' => 'nullable|string|max:100',
        ]));
        ActivityLog::record('supplier.created', "Added supplier {$s->name}", $s);

        return response()->json($s, 201);
    }

    public function customers()
    {
        return Customer::withCount('orders')->withSum(['orders as revenue' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->withMax('orders as last_order', 'ordered_at')->orderBy('name')->get();
    }

    public function storeCustomer(Request $request)
    {
        $c = Customer::create($request->validate([
            'name' => 'required|string|max:100', 'type' => 'required|in:supermarket,retailer,wholesaler,hotel,market',
            'phone' => 'nullable|string|max:30', 'location' => 'nullable|string|max:100',
        ]));
        ActivityLog::record('customer.created', "Added customer {$c->name}", $c);

        return response()->json($c, 201);
    }
}
