<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sparepart;
use App\Models\SparepartStock;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SparepartController extends Controller
{
    /**
     * Menampilkan daftar suku cadang mesin percetakan & packaging.
     * GET /api/v1/spareparts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sparepart::with([
            'category:id,name',
            'supplier:id,name,code',
            'stocks.warehouse:id,name,type',
        ]);

        // Filter search kode / nama / tipe mesin
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('machine', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan kategori
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter berdasarkan supplier
        if ($request->filled('supplier_id') && $request->input('supplier_id') !== 'all') {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter berdasarkan mesin
        if ($request->filled('machine') && $request->input('machine') !== 'all') {
            $query->where('machine', 'like', "%" . $request->input('machine') . "%");
        }

        $spareparts = $query->orderBy('name', 'asc')->get();

        $data = $spareparts->map(function ($s) {
            $stocks = $s->stocks;
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

            $isBelowRop = $totalAvailable <= (float) $s->reorder_point;
            $isBelowSafety = $totalAvailable <= (float) $s->safety_stock;

            $status = 'NORMAL';
            if ($isBelowSafety) {
                $status = 'CRITICAL';
            } elseif ($isBelowRop) {
                $status = 'WARNING';
            }

            return [
                'id'               => $s->id,
                'code'             => $s->code,
                'name'             => $s->name,
                'category_id'      => $s->category_id,
                'category_name'    => $s->category?->name ?? 'Sparepart Umum',
                'supplier_id'      => $s->supplier_id,
                'supplier_name'    => $s->supplier?->name ?? '-',
                'unit'             => $s->unit,
                'machine'          => $s->machine,
                'safety_stock'     => (float) $s->safety_stock,
                'reorder_point'    => (float) $s->reorder_point,
                'batch_no'         => $s->batch_no,
                'expiry_date'      => $s->expiry_date ? Carbon::parse($s->expiry_date)->format('Y-m-d') : null,
                'total_available'  => $totalAvailable,
                'total_reserved'   => $totalReserved,
                'rop_status'       => $status,
                'is_low_stock'     => $isBelowRop,
                'warehouse_stocks' => $warehouseBreakdown,
                'created_at'       => $s->created_at?->toIso8601String(),
                'updated_at'       => $s->updated_at?->toIso8601String(),
            ];
        });

        if ($request->boolean('low_stock_only') || $request->input('status') === 'rop') {
            $data = $data->filter(fn ($item) => $item['is_low_stock'])->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar suku cadang mesin berhasil dimuat',
            'data'    => $data,
            'meta'    => [
                'total'     => $data->count(),
                'low_stock' => $data->where('is_low_stock', true)->count(),
            ],
        ]);
    }

    /**
     * Menyimpan data master suku cadang baru.
     * POST /api/v1/spareparts
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'unique:spareparts,code'],
            'name'           => ['required', 'string', 'max:150'],
            'category_id'    => ['nullable', 'integer', 'exists:categories,id'],
            'unit'           => ['required', 'string', 'max:20'],
            'machine'        => ['nullable', 'string', 'max:100'],
            'supplier_id'    => ['nullable', 'integer', 'exists:suppliers,id'],
            'safety_stock'   => ['nullable', 'numeric', 'min:0'],
            'reorder_point'  => ['nullable', 'numeric', 'min:0'],
            'batch_no'       => ['nullable', 'string', 'max:100'],
            'expiry_date'    => ['nullable', 'date'],
            'initial_stocks' => ['nullable', 'array'],
            'initial_stocks.*.warehouse_id'  => ['required_with:initial_stocks', 'integer', 'exists:warehouses,id'],
            'initial_stocks.*.qty_available' => ['required_with:initial_stocks', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {
            $sparepart = Sparepart::create([
                'code'          => strtoupper(trim($validated['code'])),
                'name'          => trim($validated['name']),
                'category_id'   => $validated['category_id'] ?? null,
                'unit'          => $validated['unit'],
                'machine'       => $validated['machine'] ?? null,
                'supplier_id'   => $validated['supplier_id'] ?? null,
                'safety_stock'  => $validated['safety_stock'] ?? 0.00,
                'reorder_point' => $validated['reorder_point'] ?? 0.00,
                'batch_no'      => $validated['batch_no'] ?? null,
                'expiry_date'   => $validated['expiry_date'] ?? null,
            ]);

            // Inisialisasi stok fisik per gudang
            $warehouses = Warehouse::all();
            $initialStockMap = collect($validated['initial_stocks'] ?? [])->keyBy('warehouse_id');

            foreach ($warehouses as $wh) {
                $qty = 0.00;
                if ($initialStockMap->has($wh->id)) {
                    $qty = (float) $initialStockMap->get($wh->id)['qty_available'];
                }

                SparepartStock::create([
                    'sparepart_id'  => $sparepart->id,
                    'warehouse_id'  => $wh->id,
                    'qty_available' => $qty,
                    'qty_reserved'  => 0.00,
                ]);
            }

            $sparepart->load(['category', 'supplier', 'stocks.warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Suku cadang baru berhasil ditambahkan',
                'data'    => $sparepart,
            ], 201);
        });
    }

    /**
     * Menampilkan detail satu suku cadang.
     * GET /api/v1/spareparts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $sparepart = Sparepart::with([
            'category',
            'supplier',
            'stocks.warehouse',
        ])->find($id);

        if (!$sparepart) {
            return response()->json([
                'success' => false,
                'message' => "Suku cadang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail suku cadang berhasil diambil',
            'data'    => $sparepart,
        ]);
    }

    /**
     * Memperbarui data suku cadang.
     * PUT/PATCH /api/v1/spareparts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sparepart = Sparepart::find($id);

        if (!$sparepart) {
            return response()->json([
                'success' => false,
                'message' => "Suku cadang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'code'          => ['sometimes', 'required', 'string', 'max:50', Rule::unique('spareparts', 'code')->ignore($sparepart->id)],
            'name'          => ['sometimes', 'required', 'string', 'max:150'],
            'category_id'   => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'unit'          => ['sometimes', 'required', 'string', 'max:20'],
            'machine'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'supplier_id'   => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'safety_stock'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reorder_point' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'batch_no'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'expiry_date'   => ['sometimes', 'nullable', 'date'],
        ]);

        $sparepart->update([
            'code'          => isset($validated['code']) ? strtoupper(trim($validated['code'])) : $sparepart->code,
            'name'          => isset($validated['name']) ? trim($validated['name']) : $sparepart->name,
            'category_id'   => array_key_exists('category_id', $validated) ? $validated['category_id'] : $sparepart->category_id,
            'unit'          => $validated['unit'] ?? $sparepart->unit,
            'machine'       => array_key_exists('machine', $validated) ? $validated['machine'] : $sparepart->machine,
            'supplier_id'   => array_key_exists('supplier_id', $validated) ? $validated['supplier_id'] : $sparepart->supplier_id,
            'safety_stock'  => array_key_exists('safety_stock', $validated) ? ($validated['safety_stock'] ?? 0.00) : $sparepart->safety_stock,
            'reorder_point' => array_key_exists('reorder_point', $validated) ? ($validated['reorder_point'] ?? 0.00) : $sparepart->reorder_point,
            'batch_no'      => array_key_exists('batch_no', $validated) ? $validated['batch_no'] : $sparepart->batch_no,
            'expiry_date'   => array_key_exists('expiry_date', $validated) ? $validated['expiry_date'] : $sparepart->expiry_date,
        ]);

        $sparepart->load(['category', 'supplier', 'stocks.warehouse']);

        return response()->json([
            'success' => true,
            'message' => 'Suku cadang berhasil diperbarui',
            'data'    => $sparepart,
        ]);
    }

    /**
     * Menghapus suku cadang dengan proteksi integritas stok.
     * DELETE /api/v1/spareparts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $sparepart = Sparepart::find($id);

        if (!$sparepart) {
            return response()->json([
                'success' => false,
                'message' => "Suku cadang dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        // Cek apakah ada stok aktif > 0
        $totalStock = $sparepart->stocks()->sum('qty_available');
        if ($totalStock > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat menghapus suku cadang yang masih memiliki stok fisik ({$totalStock} {$sparepart->unit}). Lakukan penyesuaian stok terlebih dahulu.",
                'error_code' => 'ACTIVE_STOCK_EXISTS',
            ], 400);
        }

        return DB::transaction(function () use ($sparepart) {
            $sparepart->stocks()->delete();
            $sparepart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Suku cadang berhasil dihapus',
            ]);
        });
    }
}
