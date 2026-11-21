<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StorageLocation;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $q = StorageLocation::query()
            ->withSum(['batches as load_kg' => fn ($b) => $b->inStock()], 'remaining_kg')
            ->with(['batches' => fn ($b) => $b->inStock()->with('supplier:id,name')->orderBy('arrived_at')]);
        if ($zone = $request->query('zone')) {
            $q->where('zone', $zone);
        }

        return $q->orderBy('id')->get()->map(fn ($l) => $l->setAttribute('live', $l->liveReading())->setAttribute('load_kg', (int) $l->load_kg));
    }

    public function show(StorageLocation $location)
    {
        $location->load(['batches' => fn ($b) => $b->inStock()->with('supplier:id,name')]);
        $location->setAttribute('live', $location->liveReading());
        $location->setAttribute('history', $location->readings()->where('recorded_at', '>=', now()->subHours(48))->orderBy('recorded_at')->get());

        return $location;
    }

    public function update(Request $request, StorageLocation $location)
    {
        $data = $request->validate([
            'status' => 'sometimes|in:active,gassing,venting,idle,maintenance',
            'target_temp' => 'sometimes|numeric|min:5|max:30',
            'target_humidity' => 'sometimes|numeric|min:50|max:100',
            'target_ethylene' => 'sometimes|integer|min:0|max:1000',
        ]);
        $location->update($data);
        $changes = collect($data)->map(fn ($v, $k) => str_replace('target_', '', $k).'='.$v)->implode(', ');
        ActivityLog::record('room.adjusted', "Adjusted {$location->name}: {$changes}", $location);

        return $location->setAttribute('live', $location->liveReading());
    }
}
