<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MaterialCollection;
use App\Http\Resources\Api\V1\MaterialResource;
use App\Models\Material;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /**
     * The relations to eager-load on every material fetch.
     * Keeping this in one place avoids N+1 on all actions.
     */
    private const WITH = ['category', 'unitRelation', 'brands', 'warehouseStocks.warehouse', 'supplier', 'batchLots'];

    /**
     * List materials with filters.
     *
     * Query parameters:
     *   item_type   – 'material' | 'product' | 'sparepart'  (default: all)
     *   category_id – integer ID
     *   brand_id    – integer ID (filters via pivot)
     *   warehouse_id – integer ID (only items with stock in that warehouse)
     *   search      – searches sku and name (ILIKE)
     *   low_stock   – '1' to return only items ≤ reorder_point
     *   per_page    – integer (default 50, max 200)
     */
    public function index(Request $request): MaterialCollection
    {
        $perPage = min((int) ($request->per_page ?? 50), 200);

        $query = Material::with(self::WITH)
            ->when($request->item_type, fn ($q) => $q->where('item_type', $request->item_type))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when(
                $request->brand_id,
                fn ($q) => $q->whereHas('brands', fn ($b) => $b->where('brands.id', $request->brand_id))
            )
            ->when(
                $request->warehouse_id,
                fn ($q) => $q->whereHas(
                    'warehouseStocks',
                    fn ($ws) => $ws->where('warehouse_id', $request->warehouse_id)->where('qty_available', '>', 0)
                )
            )
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                    ->orWhere('sku', 'ilike', "%{$request->search}%");
            }))
            ->when($request->low_stock, function ($q) {
                // Only items where total available stock ≤ reorder_point
                $q->whereHas(
                    'warehouseStocks',
                    fn ($ws) => $ws->whereColumn('qty_available', '<=', 'materials.reorder_point')
                );
            });

        return new MaterialCollection($query->paginate($perPage));
    }

    /**
     * Store a new material.
     *
     * Accepts:
     *   sku, name, item_type, category_id, unit_id, unit,
     *   safety_stock, reorder_point, supplier_id,
     *   gramature, dimension, machine,
     *   brand_ids: int[]
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku'           => ['required', 'string', 'max:50', 'unique:materials,sku'],
            'name'          => ['required', 'string', 'max:150'],
            'item_type'     => ['nullable', 'string', 'in:material,product,sparepart'],
            'category_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'unit_id'       => ['nullable', 'integer', 'exists:units,id'],
            'unit'          => ['nullable', 'string', 'max:20'],
            'safety_stock'  => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'supplier_id'   => ['nullable', 'integer', 'exists:suppliers,id'],
            'gramature'     => ['nullable', 'integer', 'min:1'],
            'dimension'     => ['nullable', 'string', 'max:50'],
            'machine'       => ['nullable', 'string', 'max:100'],
            'brand_ids'     => ['nullable', 'array'],
            'brand_ids.*'   => ['integer', 'exists:brands,id'],
        ]);

        $material = Material::create($validated);

        if (! empty($validated['brand_ids'])) {
            $material->brands()->sync($validated['brand_ids']);
        }

        $material->load(self::WITH);

        return (new MaterialResource($material))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single material with full stock and batch detail.
     */
    public function show(Material $material): MaterialResource
    {
        $material->load(self::WITH);

        return new MaterialResource($material);
    }

    /**
     * Update material master data.
     */
    public function update(Request $request, Material $material): MaterialResource
    {
        $validated = $request->validate([
            'sku'           => ['sometimes', 'string', 'max:50', "unique:materials,sku,{$material->id}"],
            'name'          => ['sometimes', 'string', 'max:150'],
            'item_type'     => ['sometimes', 'string', 'in:material,product,sparepart'],
            'category_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'unit_id'       => ['nullable', 'integer', 'exists:units,id'],
            'unit'          => ['sometimes', 'string', 'max:20'],
            'safety_stock'  => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'supplier_id'   => ['nullable', 'integer', 'exists:suppliers,id'],
            'gramature'     => ['nullable', 'integer', 'min:1'],
            'dimension'     => ['nullable', 'string', 'max:50'],
            'machine'       => ['nullable', 'string', 'max:100'],
            'brand_ids'     => ['nullable', 'array'],
            'brand_ids.*'   => ['integer', 'exists:brands,id'],
        ]);

        $material->update($validated);

        if (array_key_exists('brand_ids', $validated)) {
            $material->brands()->sync($validated['brand_ids'] ?? []);
        }

        $material->load(self::WITH);

        return new MaterialResource($material);
    }

    /**
     * Delete a material (only allowed when all warehouse stocks are zero).
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

        return response()->json(['message' => 'Material deleted successfully.']);
    }

    /**
     * Return materials whose total stock ≤ reorder_point (ROP Alert engine).
     *
     * Query parameters:
     *   warehouse_id – scope to a specific warehouse
     *   item_type    – scope to a specific item kind
     */
    public function lowStock(Request $request): JsonResponse
    {
        $query = Material::with(self::WITH)
            ->when($request->item_type, fn ($q) => $q->where('item_type', $request->item_type))
            ->whereHas('warehouseStocks', function ($q) use ($request) {
                $q->whereColumn('qty_available', '<=', 'materials.reorder_point');
                if ($request->warehouse_id) {
                    $q->where('warehouse_id', $request->warehouse_id);
                }
            });

        $items = $query->get();

        return response()->json([
            'data'  => MaterialResource::collection($items),
            'count' => $items->count(),
        ]);
    }

    /**
     * Stock mutation history for a specific material.
     *
     * Query parameters:
     *   limit – max records to return (default 20, max 100)
     */
    public function movements(Material $material, Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 20), 100);

        $mutations = $material->stockMutations()
            ->with(['createdBy:id,name', 'originWarehouse:id,name', 'targetWarehouse:id,name'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'id'          => (string) $m->id,
                'type'        => $m->type,                          // IN/OUT/TRANSFER/ADJUSTMENT
                'qty'         => (float) $m->qty,
                'warehouse'   => 'g'.($m->origin_warehouse_id ?? $m->target_warehouse_id),
                'toWarehouse' => $m->target_warehouse_id ? 'g'.$m->target_warehouse_id : null,
                'reference'   => $m->reference_type.'#'.$m->reference_id,
                'note'        => null,                              // reserved for future column
                'user'        => $m->createdBy?->name ?? 'System',
                'date'        => $m->created_at->toISOString(),
            ]);

        return response()->json([
            'data'     => $mutations,
            'material' => [
                'id'   => (string) $material->id,
                'code' => $material->sku,
                'name' => $material->name,
            ],
        ]);
    }
}
