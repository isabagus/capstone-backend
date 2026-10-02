<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Packsolution ERP/CRM Multi-Brand & Multi-Gudang
|--------------------------------------------------------------------------
| Base URL Prefix: /api
*/

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'system'    => 'Packsolution ERP/CRM API',
        'version'   => '1.0.0',
        'database'  => config('database.default'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Helper closure to register Master Data routes
$registerMasterRoutes = function () {
    // Materials CRUD (MOD-01)
    Route::apiResource('materials', MaterialController::class);
    Route::get('inventory/materials', [MaterialController::class, 'index']);
    Route::get('inventory/alerts/rop', function (Request $request, MaterialController $controller) {
        $request->merge(['low_stock_only' => true]);
        return $controller->index($request);
    });

    // Warehouses CRUD
    Route::apiResource('warehouses', WarehouseController::class);

    // Brands CRUD
    Route::apiResource('brands', BrandController::class);

    // Categories CRUD
    Route::apiResource('categories', CategoryController::class);

    // Suppliers CRUD
    Route::apiResource('suppliers', SupplierController::class);
};

// 1. Versioned Routes: /api/v1/...
Route::prefix('v1')->group(function () use ($registerMasterRoutes) {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/login',   [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me',      [AuthController::class, 'me']);
        });
    });

    // Master Data
    $registerMasterRoutes();
});

// 2. Direct Routes: /api/... (Alias for easy access and backward compatibility)
Route::prefix('auth')->group(function () {
    Route::post('/login',   [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

$registerMasterRoutes();
