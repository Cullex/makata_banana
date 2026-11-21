<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| JSON API — served from the same origin as the SPA, so it uses the regular
| session cookie + CSRF token (axios sends X-XSRF-TOKEN automatically).
*/
Route::prefix('api')->group(function () {
    Route::post('login', [Api\AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [Api\AuthController::class, 'logout']);
        Route::get('me', [Api\AuthController::class, 'me']);
        Route::get('meta', [Api\MetaController::class, 'index']);
        Route::get('alerts', [Api\MetaController::class, 'alerts']);
        Route::get('dashboard', Api\DashboardController::class);

        Route::get('batches', [Api\BatchController::class, 'index']);
        Route::get('batches/{batch}', [Api\BatchController::class, 'show']);
        Route::get('locations', [Api\LocationController::class, 'index']);
        Route::get('locations/{location}', [Api\LocationController::class, 'show']);
        Route::get('inventory', [Api\InventoryController::class, 'summary']);
        Route::get('inventory/movements', [Api\InventoryController::class, 'movements']);
        Route::get('inventory/losses', [Api\InventoryController::class, 'losses']);
        Route::get('orders', [Api\OrderController::class, 'index']);
        Route::get('orders/{order}', [Api\OrderController::class, 'show']);
        Route::get('prices', [Api\PriceRuleController::class, 'index']);
        Route::get('suppliers', [Api\PartnerController::class, 'suppliers']);
        Route::get('customers', [Api\PartnerController::class, 'customers']);

        // Warehouse floor: receiving, ripening, movements, losses
        Route::middleware('role:warehouse,inspector')->group(function () {
            Route::post('batches', [Api\BatchController::class, 'store']);
            Route::post('batches/{batch}/inspect', [Api\BatchController::class, 'inspect']);
            Route::patch('batches/{batch}/stage', [Api\BatchController::class, 'updateStage']);
            Route::post('batches/{batch}/move', [Api\BatchController::class, 'move']);
            Route::post('batches/{batch}/loss', [Api\BatchController::class, 'loss']);
            Route::patch('locations/{location}', [Api\LocationController::class, 'update']);
            Route::post('suppliers', [Api\PartnerController::class, 'storeSupplier']);
        });

        // Sales desk
        Route::middleware('role:sales')->group(function () {
            Route::post('orders/preview', [Api\OrderController::class, 'preview']);
            Route::post('orders', [Api\OrderController::class, 'store']);
            Route::patch('orders/{order}/status', [Api\OrderController::class, 'updateStatus']);
            Route::post('customers', [Api\PartnerController::class, 'storeCustomer']);
        });

        // Managers only
        Route::middleware('role:manager')->group(function () {
            Route::get('reports', Api\ReportController::class);
            Route::patch('prices/{rule}', [Api\PriceRuleController::class, 'update']);
            Route::get('users', [Api\UserController::class, 'index']);
            Route::post('users', [Api\UserController::class, 'store']);
            Route::patch('users/{user}', [Api\UserController::class, 'update']);
            Route::get('activity', [Api\UserController::class, 'activity']);
        });
    });

    Route::fallback(fn () => response()->json(['message' => 'Not found'], 404));
});

// Everything else is handled by the Vue router.
Route::view('/login', 'app')->name('login');
Route::view('/{any?}', 'app')->where('any', '^(?!api|build|up).*$');
