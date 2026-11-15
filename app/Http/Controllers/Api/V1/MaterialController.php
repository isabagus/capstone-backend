<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    /**
     * Display a listing of materials with stock levels and ROP indicators.
     * GET /api/v1/materials or /api/materials
     */
    public function index(Request $request): JsonResponse
    {
        $query = Material::with([
            'category:id,name',
            'supplier:id,name,code',
            'brands:id,name,code,slug',
            'warehouseStocks.warehouse:id,name,type',
        ]);

        // Filter search SKU or Name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by Category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by Supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter by Brand
        if ($request->filled('brand_id')) {
            $brandId = $request->input('brand_id');
            $query->whereHas('brands', function ($q) use ($brandId) {
                $q->where('brands.id', $brandId);
            });
        }

        $materials = $query->orderBy('name', 'asc')->get();

        // Transform with calculated fields (total stock, stock per warehouse, ROP status, expiry status)
        $data = $materials->map(function ($material) use ($request) {
            $stocks = $material->warehouseStocks;
            $totalAvailable = (float) $stocks->sum('qty_available');
            $totalReserved = (float) $stocks->sum('qty_reserved');

            $warehouseBreakdown = $stocks->map(function ($ws) {
                return [
                    'warehouse_id'   => $ws->warehouse_id,
                    'warehouse_name' => $ws->warehouse?->name,
                    'warehouse_type' => $ws->warehouse?->type,
                    'qty_available'  => (float) $ws->qty_available,
                    'qty_reserved'   => (float) $ws->qty_reserved,
                ];
            });

            $isBelowRop = $totalAvailable <= (float) $material->reorder_point;
            $isBelowSafety = $totalAvailable <= (float) $material->safety_stock;

            $status = 'NORMAL';
            if ($isBelowSafety) {
                $status = 'CRITICAL';
            } elseif ($isBelowRop) {
                $status = 'WARNING';
            }

            // Hitung status kedaluwarsa (< 30 hari warning untuk tinta/bahan kimia)
            $expiryStatus = 'none';
            if ($material->expiry_date) {
                $exp = \Carbon\Carbon::parse($material->expiry_date);
                if ($exp->isPast()) {
                    $expiryStatus = 'expired';
                } elseif ($exp->diffInDays(now()) <= 30) {
                    $expiryStatus = 'soon';
                } else {
                    $expiryStatus = 'ok';
                }
            }

            return [
                'id'                 => $material->id,
                'sku'                => $material->sku,
                'name'               => $material->name,
                'category_id'        => $material->category_id,
                'category_name'      => $material->category?->name,
                'supplier_id'        => $material->supplier_id,
                'supplier_name'      => $material->supplier?->name,
                'unit'               => $material->unit,
                'gramature'          => $material->gramature ? (float) $material->gramature : null,
                'dimension'          => $material->dimension,
                'batch_no'           => $material->batch_no,
                'expiry_date'        => $material->expiry_date ? \Carbon\Carbon::parse($material->expiry_date)->format('Y-m-d') : null,
                'expiry_status'      => $expiryStatus,
                'safety_stock'       => (float) $material->safety_stock,
                'reorder_point'      => (float) $material->reorder_point,
                'total_available'    => $totalAvailable,
                'total_reserved'     => $totalReserved,
                'rop_status'         => $status,
                'is_low_stock'       => $isBelowRop,
                'brands'             => $material->brands,
                'warehouse_stocks'   => $warehouseBreakdown,
                'created_at'         => $material->created_at?->toIso8601String(),
                'updated_at'         => $material->updated_at?->toIso8601String(),
            ];
        });

        // Optional filter for low stock only
        if ($request->boolean('low_stock_only') || $request->input('status') === 'rop') {
            $data = $data->filter(fn ($item) => $item['is_low_stock'])->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar data master material berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'total'     => $data->count(),
                'low_stock' => $data->where('is_low_stock', true)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created material.
     * POST /api/v1/materials
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku'             => ['required', 'string', 'max:50', 'unique:materials,sku'],
            'name'            => ['required', 'string', 'max:150'],
            'category_id'     => ['required', 'integer', 'exists:categories,id'],
            'supplier_id'     => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit'            => ['required', 'string', 'max:20'],
            'gramature'       => ['nullable', 'numeric', 'min:0'],
            'dimension'       => ['nullable', 'string', 'max:100'],
            'batch_no'        => ['nullable', 'string', 'max:100'],
            'expiry_date'     => ['nullable', 'date'],
            'safety_stock'    => ['nullable', 'numeric', 'min:0'],
            'reorder_point'   => ['nullable', 'numeric', 'min:0'],
            'brand_ids'       => ['nullable', 'array'],
            'brand_ids.*'     => ['integer', 'exists:brands,id'],
            'initial_stocks'  => ['nullable', 'array'],
            'initial_stocks.*.warehouse_id' => ['required_with:initial_stocks', 'integer', 'exists:warehouses,id'],
            'initial_stocks.*.qty_available' => ['required_with:initial_stocks', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {
            $material = Material::create([
                'sku'           => strtoupper(trim($validated['sku'])),
                'name'          => trim($validated['name']),
                'category_id'   => $validated['category_id'],
                'supplier_id'   => $validated['supplier_id'] ?? null,
                'unit'          => $validated['unit'],
                'gramature'     => $validated['gramature'] ?? null,
                'dimension'     => $validated['dimension'] ?? null,
                'batch_no'      => $validated['batch_no'] ?? null,
                'expiry_date'   => $validated['expiry_date'] ?? null,
                'safety_stock'  => $validated['safety_stock'] ?? 0.00,
                'reorder_point' => $validated['reorder_point'] ?? 0.00,
            ]);

            // Sync brands if provided
            if (!empty($validated['brand_ids'])) {
                $material->brands()->sync($validated['brand_ids']);
            }

            // Inisialisasi warehouse stock untuk setiap gudang
            $warehouses = Warehouse::all();
            $initialStockMap = collect($validated['initial_stocks'] ?? [])
                ->keyBy('warehouse_id');

            foreach ($warehouses as $wh) {
                $initialQty = 0.00;
                if ($initialStockMap->has($wh->id)) {
                    $initialQty = (float) $initialStockMap->get($wh->id)['qty_available'];
                }

                WarehouseStock::create([
                    'material_id'   => $material->id,
                    'warehouse_id'  => $wh->id,
                    'qty_available' => $initialQty,
                    'qty_reserved'  => 0.00,
                ]);
            }

            $material->load(['category', 'supplier', 'brands', 'warehouseStocks.warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Material baru berhasil ditambahkan',
                'data'    => $material,
            ], 201);
        });
    }

    /**
     * Display the specified material.
     * GET /api/v1/materials/{id}
     */
    public function show(int $id): JsonResponse
    {
        $material = Material::with([
            'category',
            'supplier',
            'brands',
            'warehouseStocks.warehouse',
            'batchLots',
        ])->find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => "Material dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail material berhasil diambil',
            'data'    => $material,
        ]);
    }

    /**
     * Update the specified material.
     * PUT/PATCH /api/v1/materials/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => "Material dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'sku'           => ['sometimes', 'required', 'string', 'max:50', Rule::unique('materials', 'sku')->ignore($material->id)],
            'name'          => ['sometimes', 'required', 'string', 'max:150'],
            'category_id'   => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'supplier_id'   => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'unit'          => ['sometimes', 'required', 'string', 'max:20'],
            'gramature'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'dimension'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'batch_no'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'expiry_date'   => ['sometimes', 'nullable', 'date'],
            'safety_stock'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reorder_point' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'brand_ids'     => ['nullable', 'array'],
            'brand_ids.*'   => ['integer', 'exists:brands,id'],
        ]);

        return DB::transaction(function () use ($material, $validated) {
            $material->update([
                'sku'           => isset($validated['sku']) ? strtoupper(trim($validated['sku'])) : $material->sku,
                'name'          => isset($validated['name']) ? trim($validated['name']) : $material->name,
                'category_id'   => $validated['category_id'] ?? $material->category_id,
                'supplier_id'   => array_key_exists('supplier_id', $validated) ? $validated['supplier_id'] : $material->supplier_id,
                'unit'          => $validated['unit'] ?? $material->unit,
                'gramature'     => array_key_exists('gramature', $validated) ? $validated['gramature'] : $material->gramature,
                'dimension'     => array_key_exists('dimension', $validated) ? $validated['dimension'] : $material->dimension,
                'batch_no'      => array_key_exists('batch_no', $validated) ? $validated['batch_no'] : $material->batch_no,
                'expiry_date'   => array_key_exists('expiry_date', $validated) ? $validated['expiry_date'] : $material->expiry_date,
                'safety_stock'  => array_key_exists('safety_stock', $validated) ? ($validated['safety_stock'] ?? 0.00) : $material->safety_stock,
                'reorder_point' => array_key_exists('reorder_point', $validated) ? ($validated['reorder_point'] ?? 0.00) : $material->reorder_point,
            ]);

            if (array_key_exists('brand_ids', $validated)) {
                $material->brands()->sync($validated['brand_ids'] ?? []);
            }

            $material->load(['category', 'supplier', 'brands', 'warehouseStocks.warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil diperbarui',
                'data'    => $material,
            ]);
        });
    }

    /**
     * Remove the specified material.
     * DELETE /api/v1/materials/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => "Material dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        // Cek apakah ada stok aktif > 0
        $totalStock = $material->warehouseStocks()->sum('qty_available');
        if ($totalStock > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat menghapus material yang masih memiliki stok fisik ({$totalStock} {$material->unit}). Lakukan penyesuaian stok terlebih dahulu.",
                'error_code' => 'ACTIVE_STOCK_EXISTS',
            ], 400);
        }

        return DB::transaction(function () use ($material) {
            $material->brands()->detach();
            $material->warehouseStocks()->delete();
            $material->delete();

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil dihapus',
            ]);
        });
    }
}
