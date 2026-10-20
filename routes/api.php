<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SparepartController;
use App\Http\Controllers\Api\V1\StockMutationController;
use App\Http\Controllers\Api\V1\StockOpnameController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitConversionController;
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

    // Spareparts CRUD (INV-03 / TSK-GDG-06)
    Route::apiResource('spareparts', SparepartController::class);
    Route::get('inventory/spareparts', [SparepartController::class, 'index']);

    // Products CRUD (INV-02 / TSK-GDG-07)
    Route::apiResource('products', ProductController::class);
    Route::get('inventory/products', [ProductController::class, 'index']);

    // Stock Opname Reconciliation (INV-04 / TSK-GDG-08)
    Route::apiResource('stock-opnames', StockOpnameController::class);
    Route::post('stock-opnames/{id}/count', [StockOpnameController::class, 'updateCounts']);
    Route::post('stock-opnames/{id}/reconcile', [StockOpnameController::class, 'reconcile']);

    // Stock Mutations & ACID Operations (TSK-GDG-04)
    Route::prefix('inventory')->group(function () {
        Route::get('movements', [StockMutationController::class, 'index']);
        Route::post('in', [StockMutationController::class, 'stockIn']);
        Route::post('out', [StockMutationController::class, 'stockOut']);
        Route::post('transfer', [StockMutationController::class, 'transfer']);
        Route::post('opname', [StockMutationController::class, 'stockOpname']);
    });

    // Warehouses CRUD
    Route::apiResource('warehouses', WarehouseController::class);

    // Brands CRUD
    Route::apiResource('brands', BrandController::class);

    // Categories CRUD
    Route::apiResource('categories', CategoryController::class);

    // Suppliers CRUD
    Route::apiResource('suppliers', SupplierController::class);

    // Units CRUD
    Route::apiResource('units', UnitController::class);

    // Unit Conversions CRUD
    Route::apiResource('unit-conversions', UnitConversionController::class);
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
