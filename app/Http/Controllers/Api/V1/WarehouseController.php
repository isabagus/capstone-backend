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
                'id'          => (string) $wh->id,
                'code'        => $wh->code ?? 'WH-' . $wh->id,
                'name'        => $wh->name,
                'shortName'   => $wh->short_name ?? $wh->name,
                'short_name'  => $wh->short_name ?? $wh->name,
                'address'     => $wh->address,
                'phone'       => $wh->phone,
                'picName'     => $wh->pic_name,
                'pic_name'    => $wh->pic_name,
                'type'        => $wh->type,
                'description' => $wh->description,
                'active'      => (bool) ($wh->is_active ?? true),
                'total_skus'  => $wh->total_skus ?? 0,
                'totalSku'    => $wh->total_skus ?? 0,
                'total_units' => $totalUnits,
                'totalStock'  => $totalUnits,
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
            'name'        => ['required', 'string', 'max:100', 'unique:warehouses,name'],
            'code'        => ['nullable', 'string', 'max:20', 'unique:warehouses,code'],
            'short_name'  => ['nullable', 'string', 'max:50'],
            'shortName'   => ['nullable', 'string', 'max:50'],
            'address'     => ['nullable', 'string'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'pic_name'    => ['nullable', 'string', 'max:100'],
            'picName'     => ['nullable', 'string', 'max:100'],
            'type'        => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'active'      => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $shortName = $validated['shortName'] ?? ($validated['short_name'] ?? null);
        $picName   = $validated['picName'] ?? ($validated['pic_name'] ?? null);
        $isActive  = $validated['active'] ?? ($validated['is_active'] ?? true);

        return DB::transaction(function () use ($validated, $shortName, $picName, $isActive) {
            $warehouse = Warehouse::create([
                'name'        => trim($validated['name']),
                'code'        => !empty($validated['code']) ? strtoupper(trim($validated['code'])) : null,
                'short_name'  => $shortName ? trim($shortName) : null,
                'address'     => isset($validated['address']) ? trim($validated['address']) : null,
                'phone'       => isset($validated['phone']) ? trim($validated['phone']) : null,
                'pic_name'    => $picName ? trim($picName) : null,
                'type'        => $validated['type'],
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
                'is_active'   => $isActive,
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
            'name'        => ['sometimes', 'required', 'string', 'max:100', Rule::unique('warehouses', 'name')->ignore($warehouse->id)],
            'code'        => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'short_name'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'shortName'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'     => ['nullable', 'string'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'pic_name'    => ['nullable', 'string', 'max:100'],
            'picName'     => ['nullable', 'string', 'max:100'],
            'type'        => ['sometimes', 'required', 'string'],
            'description' => ['nullable', 'string'],
            'active'      => ['sometimes', 'nullable', 'boolean'],
            'is_active'   => ['sometimes', 'nullable', 'boolean'],
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = trim($validated['name']);
        if (array_key_exists('code', $validated)) $updateData['code'] = $validated['code'] ? strtoupper(trim($validated['code'])) : null;
        if (isset($validated['shortName'])) $updateData['short_name'] = trim($validated['shortName']);
        elseif (isset($validated['short_name'])) $updateData['short_name'] = trim($validated['short_name']);
        if (array_key_exists('address', $validated)) $updateData['address'] = $validated['address'] ? trim($validated['address']) : null;
        if (array_key_exists('phone', $validated)) $updateData['phone'] = $validated['phone'] ? trim($validated['phone']) : null;
        if (isset($validated['picName'])) $updateData['pic_name'] = trim($validated['picName']);
        elseif (isset($validated['pic_name'])) $updateData['pic_name'] = trim($validated['pic_name']);
        if (isset($validated['type'])) $updateData['type'] = $validated['type'];
        if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'] ? trim($validated['description']) : null;
        if (array_key_exists('active', $validated)) $updateData['is_active'] = (bool) $validated['active'];
        elseif (array_key_exists('is_active', $validated)) $updateData['is_active'] = (bool) $validated['is_active'];

        $warehouse->update($updateData);

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
