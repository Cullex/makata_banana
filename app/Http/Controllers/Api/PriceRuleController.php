<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\PriceRule;
use App\Services\Warehouse;
use Illuminate\Http\Request;

class PriceRuleController extends Controller
{
    public function index()
    {
        $rules = PriceRule::orderBy('stage')->orderBy('grade')->get();
        $stock = Batch::where('status', 'sales')->where('remaining_kg', '>', 0)->with('supplier:id,name')->fifo()->get()
            ->map(fn ($b) => $b->setAttribute('pricing', Warehouse::priceFor($b, $rules)));

        return ['rules' => $rules, 'stock' => $stock];
    }

    public function update(Request $request, PriceRule $rule)
    {
        $data = $request->validate([
            'price_per_kg' => 'required|numeric|min:0|max:1000',
            'discount_pct' => 'required|numeric|min:0|max:90',
        ]);
        $rule->update($data);
        ActivityLog::record('price.updated', "Price {$rule->stage}/grade {$rule->grade} set to {$rule->price_per_kg} (-{$rule->discount_pct}%)", $rule);

        return $rule;
    }
}
