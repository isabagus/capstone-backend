<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * List all warehouses.
     */
    public function index(): JsonResponse
    {
        return response()->json(Warehouse::all());
    }

    /**
     * Create a new warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:MAIN_WAREHOUSE,STORE_WAREHOUSE'],
        ]);

        return response()->json(Warehouse::create($validated), 201);
    }

    /**
     * Show a single warehouse with current stock summary.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load(['stocks.material.category']);

        return response()->json($warehouse);
    }

    /**
     * Update warehouse details.
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:MAIN_WAREHOUSE,STORE_WAREHOUSE'],
        ]);

        $warehouse->update($validated);

        return response()->json($warehouse);
    }

    /**
     * List current stock balances for a specific warehouse.
     */
    public function stocks(Warehouse $warehouse): JsonResponse
    {
        $stocks = $warehouse->stocks()
            ->with(['material.category', 'material.brands'])
            ->get()
            ->map(fn ($stock) => [
                'material_id' => $stock->material_id,
                'sku' => $stock->material->sku,
                'name' => $stock->material->name,
                'category' => $stock->material->category->name,
                'unit' => $stock->material->unit,
                'qty_available' => $stock->qty_available,
                'qty_reserved' => $stock->qty_reserved,
                'qty_allocatable' => $stock->qtyAllocatable(),
                'is_below_rop' => $stock->qty_available <= $stock->material->reorder_point,
                'safety_stock' => $stock->material->safety_stock,
                'reorder_point' => $stock->material->reorder_point,
            ]);

        return response()->json([
            'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name, 'type' => $warehouse->type],
            'stocks' => $stocks,
        ]);
    }
}
