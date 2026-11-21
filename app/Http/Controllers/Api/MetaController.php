<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Warehouse;

class MetaController extends Controller
{
    public function index()
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'region']),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'type']),
            'locations' => StorageLocation::orderBy('id')->get(['id', 'code', 'name', 'zone', 'status', 'capacity_kg']),
            'roles' => User::ROLES,
            'zones' => StorageLocation::ZONES,
            'varieties' => ['Cavendish', 'Williams', 'Grand Nain', 'Lady Finger', 'Red Banana'],
            'currency' => config('app.currency', 'USD'),
        ];
    }

    public function alerts()
    {
        Warehouse::autoAge();

        return Warehouse::alerts();
    }
}
