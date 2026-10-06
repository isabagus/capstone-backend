<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /**
     * List all materials with category and brands, supporting brand/category filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Material::with(['category', 'unitRelation', 'brands'])
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->unit_id, fn ($q) => $q->where('unit_id', $request->unit_id))
            ->when($request->brand_id, fn ($q) => $q->whereHas('brands', fn ($b) => $b->where('brands.id', $request->brand_id)))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                    ->orWhere('sku', 'ilike', "%{$request->search}%");
            }));

        return response()->json($query->paginate(20));
    }

    /**
     * Store a new material.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:materials,sku'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit' => ['nullable', 'string', 'max:20'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ]);

        $material = Material::create($validated);

        if (! empty($validated['brand_ids'])) {
            $material->brands()->sync($validated['brand_ids']);
        }

        return response()->json($material->load(['category', 'unitRelation', 'brands']), 201);
    }

    /**
     * Show a single material with stock info per warehouse.
     */
    public function show(Material $material): JsonResponse
    {
        $material->load(['category', 'unitRelation', 'brands', 'warehouseStocks.warehouse']);

        return response()->json($material);
    }

    /**
     * Update material master data.
     */
    public function update(Request $request, Material $material): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['sometimes', 'string', 'max:50', "unique:materials,sku,{$material->id}"],
            'name' => ['sometimes', 'string', 'max:150'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ]);

        $material->update($validated);

        if (array_key_exists('brand_ids', $validated)) {
            $material->brands()->sync($validated['brand_ids'] ?? []);
        }

        return response()->json($material->load(['category', 'unitRelation', 'brands']));
    }

    /**
     * Soft-delete a material (only if no active stock).
     */
    public function destroy(Material $material): JsonResponse
    {
        $hasStock = $material->warehouseStocks()->where('qty_available', '>', 0)->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'Cannot delete material with active stock. Please transfer or adjust stock to zero first.',
            ], 422);
        }

        $material->delete();

        return response()->json(['message' => 'Material deleted successfully.'], 200);
    }

    /**
     * List materials below their reorder point (ROP Alert engine).
     */
    public function lowStock(Request $request): JsonResponse
    {
        $warehouseId = $request->warehouse_id;

        $lowStockItems = WarehouseStock::with(['material.category', 'warehouse'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereColumn('qty_available', '<=', 'materials.reorder_point')
            ->join('materials', 'warehouse_stocks.material_id', '=', 'materials.id')
            ->select('warehouse_stocks.*')
            ->get();

        return response()->json([
            'data' => $lowStockItems,
            'count' => $lowStockItems->count(),
        ]);
    }
}
