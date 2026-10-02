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

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses with stock aggregations.
     * GET /api/v1/warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::withCount(['stocks as total_skus' => function ($q) {
            $q->where('qty_available', '>', 0);
        }])
        ->orderBy('id', 'asc')
        ->get();

        $data = $warehouses->map(function ($wh) {
            $totalUnits = (float) $wh->stocks()->sum('qty_available');
            return [
                'id'          => $wh->id,
                'name'        => $wh->name,
                'address'     => $wh->address,
                'type'        => $wh->type,
                'total_skus'  => $wh->total_skus,
                'total_units' => $totalUnits,
                'created_at'  => $wh->created_at?->toIso8601String(),
                'updated_at'  => $wh->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar gudang berhasil diambil',
            'data'    => $data,
        ]);
    }

    /**
     * Store a newly created warehouse.
     * POST /api/v1/warehouses
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:100', 'unique:warehouses,name'],
            'address' => ['nullable', 'string'],
            'type'    => ['required', 'string', Rule::in(['MAIN_WAREHOUSE', 'STORE_WAREHOUSE'])],
        ]);

        return DB::transaction(function () use ($validated) {
            $warehouse = Warehouse::create([
                'name'    => trim($validated['name']),
                'address' => isset($validated['address']) ? trim($validated['address']) : null,
                'type'    => $validated['type'],
            ]);

            // Buat record stok 0 untuk seluruh material yang ada saat ini
            $materials = Material::all();
            foreach ($materials as $material) {
                WarehouseStock::firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'material_id'  => $material->id,
                    ],
                    [
                        'qty_available' => 0.00,
                        'qty_reserved'  => 0.00,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Gudang baru berhasil ditambahkan',
                'data'    => $warehouse,
            ], 201);
        });
    }

    /**
     * Display the specified warehouse.
     * GET /api/v1/warehouses/{id}
     */
    public function show(int $id): JsonResponse
    {
        $warehouse = Warehouse::with(['stocks.material.category'])->find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => "Gudang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail data gudang berhasil diambil',
            'data'    => $warehouse,
        ]);
    }

    /**
     * Update the specified warehouse.
     * PUT/PATCH /api/v1/warehouses/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => "Gudang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'name'    => ['sometimes', 'required', 'string', 'max:100', Rule::unique('warehouses', 'name')->ignore($warehouse->id)],
            'address' => ['nullable', 'string'],
            'type'    => ['sometimes', 'required', 'string', Rule::in(['MAIN_WAREHOUSE', 'STORE_WAREHOUSE'])],
        ]);

        $warehouse->update([
            'name'    => isset($validated['name']) ? trim($validated['name']) : $warehouse->name,
            'address' => array_key_exists('address', $validated) ? trim($validated['address']) : $warehouse->address,
            'type'    => $validated['type'] ?? $warehouse->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil diperbarui',
            'data'    => $warehouse,
        ]);
    }

    /**
     * Remove the specified warehouse.
     * DELETE /api/v1/warehouses/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => "Gudang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        // Cek jika gudang memiliki stok aktif
        $totalStock = (float) $warehouse->stocks()->sum('qty_available');
        if ($totalStock > 0) {
            return response()->json([
                'success' => false,
                'message' => "Gudang tidak dapat dihapus karena masih menampung {$totalStock} unit stok material. Kosongkan atau transfer stok terlebih dahulu.",
                'error_code' => 'WAREHOUSE_NOT_EMPTY',
            ], 400);
        }

        return DB::transaction(function () use ($warehouse) {
            $warehouse->stocks()->delete();
            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gudang berhasil dihapus',
            ]);
        });
    }
}
