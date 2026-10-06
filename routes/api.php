<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\StockMutationController;
use App\Http\Controllers\Api\V1\StockOpnameController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Auth (public) ──────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // ── Protected routes (all require valid Sanctum token) ────────────
    Route::middleware('auth:sanctum')->group(function () {

        // ── Master Data ──────────────────────────────────────────────

        // Units of Measurement — all authenticated
        Route::apiResource('units', UnitController::class);

        // Warehouses — Owner & Manager (read); Owner (write)
        Route::get('warehouses', [WarehouseController::class, 'index']);
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show']);
        Route::get('warehouses/{warehouse}/stocks', [WarehouseController::class, 'stocks']);
        Route::middleware('role:owner,manager')->group(function () {
            Route::post('warehouses', [WarehouseController::class, 'store']);
            Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update']);
        });

        // Categories — all authenticated
        Route::apiResource('categories', CategoryController::class)
            ->except(['destroy']);

        // Brands — all authenticated (read); owner,manager (write)
        Route::get('brands', [BrandController::class, 'index']);
        Route::get('brands/{brand}', [BrandController::class, 'show']);
        Route::middleware('role:owner,manager')->group(function () {
            Route::post('brands', [BrandController::class, 'store']);
            Route::put('brands/{brand}', [BrandController::class, 'update']);
        });

        // Suppliers — staf-gudang & above (read); owner,manager (write)
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::middleware('role:owner,manager')->group(function () {
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        });

        // ── Materials ────────────────────────────────────────────────

        Route::get('materials', [MaterialController::class, 'index']);
        Route::get('materials/low-stock', [MaterialController::class, 'lowStock']);
        Route::get('materials/{material}', [MaterialController::class, 'show']);
        Route::middleware('permission:inventory:create')->group(function () {
            Route::post('materials', [MaterialController::class, 'store']);
        });
        Route::middleware('permission:inventory:update')->group(function () {
            Route::put('materials/{material}', [MaterialController::class, 'update']);
        });
        Route::middleware('role:owner,manager')->group(function () {
            Route::delete('materials/{material}', [MaterialController::class, 'destroy']);
        });

        // ── Inventory Transactions ──────────────────────────────────

        // Goods Receipts — staf-gudang creates; all authenticated read
        Route::get('goods-receipts', [GoodsReceiptController::class, 'index']);
        Route::get('goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show']);
        Route::middleware('permission:inventory:create')->group(function () {
            Route::post('goods-receipts', [GoodsReceiptController::class, 'store']);
        });

        // Stock Transfers — staf-gudang creates; all authenticated read
        Route::get('stock-transfers', [StockTransferController::class, 'index']);
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);
        Route::middleware('permission:inventory:transfer')->group(function () {
            Route::post('stock-transfers', [StockTransferController::class, 'store']);
        });

        // Stock Opname — staf-gudang creates; manager/owner approves
        Route::get('stock-opnames', [StockOpnameController::class, 'index']);
        Route::get('stock-opnames/{stockOpname}', [StockOpnameController::class, 'show']);
        Route::middleware('permission:inventory:opname')->group(function () {
            Route::post('stock-opnames', [StockOpnameController::class, 'store']);
        });
        Route::middleware('role:owner,manager')->group(function () {
            Route::post('stock-opnames/{stockOpname}/approve', [StockOpnameController::class, 'approve']);
        });

        // Stock Mutations — immutable audit ledger (read-only)
        Route::get('stock-mutations', [StockMutationController::class, 'index']);
        Route::get('stock-mutations/{stockMutation}', [StockMutationController::class, 'show']);
    });
});
