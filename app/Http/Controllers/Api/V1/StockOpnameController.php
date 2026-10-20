<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockOpnameController extends Controller
{
    /**
     * Resolusi ID Gudang dari input fleksibel ("g1", "g2", "1", "2")
     */
    protected function resolveWarehouseId(mixed $value): int
    {
        $val = strtolower(trim((string) $value));
        if ($val === 'g1' || $val === '1' || str_contains($val, 'utama') || $val === 'gd-01' || $val === 'main') {
            $w = Warehouse::where('id', 1)->orWhere('code', 'GD-01')->orWhere('type', 'MAIN_WAREHOUSE')->first();
            return $w ? $w->id : 1;
        }
        if ($val === 'g2' || $val === '2' || str_contains($val, 'ruko') || $val === 'gd-02' || $val === 'store') {
            $w = Warehouse::where('id', 2)->orWhere('code', 'GD-02')->orWhere('type', 'STORE_WAREHOUSE')->first();
            return $w ? $w->id : 2;
        }

        if (is_numeric($value)) {
            $w = Warehouse::find((int) $value);
            if ($w) return $w->id;
        }

        return 1;
    }

    /**
     * Menampilkan daftar sesi stock opname fisik.
     * GET /api/v1/stock-opnames
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockOpname::with([
            'warehouse:id,name,code',
            'createdBy:id,name',
            'approvedBy:id,name',
            'details.material:id,sku,name,unit',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('warehouse_id') && $request->input('warehouse_id') !== 'all') {
            $query->where('warehouse_id', $this->resolveWarehouseId($request->input('warehouse_id')));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', strtoupper($request->input('status')));
        }

        $sessions = $query->get();

        $data = $sessions->map(function ($s) {
            $details = $s->details;
            $totalItems = $details->count();
            $discrepancyCount = $details->filter(fn ($d) => abs((float) $d->discrepancy) > 0.001)->count();
            $totalVariance = (float) $details->sum('discrepancy');

            return [
                'id'                      => $s->id,
                'opname_code'             => $s->opname_code,
                'warehouse_id'            => $s->warehouse_id,
                'warehouse_name'          => $s->warehouse?->name ?? 'Gudang ' . $s->warehouse_id,
                'warehouse_code'          => $s->warehouse?->code,
                'status'                  => $s->status,
                'notes'                   => $s->notes,
                'total_items'             => $totalItems,
                'discrepancy_items_count' => $discrepancyCount,
                'total_variance_qty'      => $totalVariance,
                'created_by'              => $s->createdBy?->name ?? 'Staf Gudang',
                'approved_by'             => $s->approvedBy?->name,
                'created_at'              => $s->created_at?->toIso8601String(),
                'completed_at'            => $s->completed_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar sesi stock opname berhasil dimuat',
            'data'    => $data,
            'meta'    => [
                'total'            => $sessions->count(),
                'pending_approval' => $sessions->where('status', 'PENDING_APPROVAL')->count(),
            ],
        ]);
    }

    /**
     * Snapshot Freeze Stock & Membuka Sesi Opname Baru.
     * POST /api/v1/stock-opnames
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required',
            'category_id'  => 'nullable|integer|exists:categories,id',
            'notes'        => 'nullable|string|max:255',
        ]);

        $warehouseId = $this->resolveWarehouseId($request->input('warehouse_id'));
        $userId = auth('sanctum')->id() ?? 1;

        return DB::transaction(function () use ($request, $warehouseId, $userId) {
            $opnameCode = 'OPN-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $opname = StockOpname::create([
                'opname_code'  => $opnameCode,
                'warehouse_id' => $warehouseId,
                'status'       => 'DRAFT',
                'notes'        => $request->input('notes', 'Sesi stock opname fisik berkala gudang'),
                'created_by'   => $userId,
            ]);

            // Query material yang relevan
            $matQuery = Material::query();
            if ($request->filled('category_id')) {
                $matQuery->where('category_id', $request->input('category_id'));
            }
            $materials = $matQuery->orderBy('id', 'asc')->get();

            // Snapshot saldo sistem saat pembekuan
            foreach ($materials as $material) {
                $stock = WarehouseStock::where('material_id', $material->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $systemQty = $stock ? (float) $stock->qty_available : 0.00;

                StockOpnameDetail::create([
                    'opname_id'    => $opname->id,
                    'material_id'  => $material->id,
                    'system_qty'   => $systemQty,
                    'physical_qty' => $systemQty, // Default sama hingga dihitung di lapangan
                    'notes'        => null,
                ]);
            }

            $opname->load(['warehouse', 'createdBy', 'details.material']);

            return response()->json([
                'success' => true,
                'message' => "Snapshot stok fisik berhasil dibekukan (Freeze Stock: {$materials->count()} item). Sesi opname {$opnameCode} berstatus DRAFT.",
                'data'    => $opname,
            ], 201);
        });
    }

    /**
     * Menampilkan detail satu sesi opname & rincian item per baris.
     * GET /api/v1/stock-opnames/{id}
     */
    public function show(int $id): JsonResponse
    {
        $opname = StockOpname::with([
            'warehouse',
            'createdBy',
            'approvedBy',
            'details.material.category',
        ])->find($id);

        if (!$opname) {
            return response()->json([
                'success' => false,
                'message' => "Sesi stock opname dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $items = $opname->details->map(function ($d) {
            return [
                'detail_id'    => $d->id,
                'material_id'  => $d->material_id,
                'sku'          => $d->material?->sku ?? '-',
                'name'         => $d->material?->name ?? 'Material #' . $d->material_id,
                'category'     => $d->material?->category?->name,
                'unit'         => $d->material?->unit ?? 'Plano',
                'system_qty'   => (float) $d->system_qty,
                'physical_qty' => (float) $d->physical_qty,
                'discrepancy'  => (float) $d->discrepancy,
                'has_variance' => abs((float) $d->discrepancy) > 0.001,
                'notes'        => $d->notes,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Detail sesi opname berhasil diambil',
            'data'    => [
                'id'                      => $opname->id,
                'opname_code'             => $opname->opname_code,
                'warehouse_id'            => $opname->warehouse_id,
                'warehouse_name'          => $opname->warehouse?->name,
                'status'                  => $opname->status,
                'notes'                   => $opname->notes,
                'created_by'              => $opname->createdBy?->name ?? 'Staf Gudang',
                'approved_by'             => $opname->approvedBy?->name,
                'created_at'              => $opname->created_at?->toIso8601String(),
                'completed_at'            => $opname->completed_at?->toIso8601String(),
                'total_items'             => $items->count(),
                'discrepancy_items_count' => $items->where('has_variance', true)->count(),
                'items'                   => $items,
            ],
        ]);
    }

    /**
     * Input hasil penghitungan fisik lapangan.
     * POST /api/v1/stock-opnames/{id}/count
     */
    public function updateCounts(Request $request, int $id): JsonResponse
    {
        $opname = StockOpname::find($id);

        if (!$opname) {
            return response()->json(['success' => false, 'message' => 'Sesi opname tidak ditemukan.'], 404);
        }

        if ($opname->status === 'COMPLETED') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi opname ini sudah selesai (COMPLETED) dan tidak dapat diubah lagi.',
            ], 422);
        }

        $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.material_id'   => 'required',
            'items.*.physical_qty'  => 'required|numeric|gte:0',
            'items.*.notes'         => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $opname) {
            foreach ($request->input('items') as $row) {
                $detail = StockOpnameDetail::where('opname_id', $opname->id)
                    ->where('material_id', $row['material_id'])
                    ->first();

                if ($detail) {
                    $detail->update([
                        'physical_qty' => (float) $row['physical_qty'],
                        'notes'        => $row['notes'] ?? $detail->notes,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Hasil hitungan fisik berhasil diperbarui.',
            ]);
        });
    }

    /**
     * Menjalankan Rekonsiliasi & Pembukuan Penyesuaian Stok (ACID).
     * POST /api/v1/stock-opnames/{id}/reconcile
     */
    public function reconcile(Request $request, int $id): JsonResponse
    {
        $opname = StockOpname::with('details.material')->find($id);

        if (!$opname) {
            return response()->json(['success' => false, 'message' => 'Sesi opname tidak ditemukan.'], 404);
        }

        if ($opname->status === 'COMPLETED') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi opname ini sudah direkonsiliasi sebelumnya.',
            ], 422);
        }

        $userId = auth('sanctum')->id() ?? 1;
        $isForceApproval = $request->boolean('force_approval');

        // Periksa apakah ada variance tinggi yang memerlukan otorisasi Manager
        // Standar industri percetakan: selisih >= 50 unit plano/lembar memerlukan approval Manager
        $hasHighVariance = false;
        foreach ($opname->details as $d) {
            if (abs((float) $d->discrepancy) >= 50) {
                $hasHighVariance = true;
                break;
            }
        }

        if ($hasHighVariance && !$isForceApproval && $opname->status !== 'APPROVED') {
            $opname->update(['status' => 'PENDING_APPROVAL']);

            return response()->json([
                'success'           => false,
                'requires_approval' => true,
                'message'           => 'Terdapat selisih stok signifikan (>= 50 unit). Sesi opname dialihkan ke status PENDING_APPROVAL untuk otorisasi Manager (Role 6).',
                'status'            => 'PENDING_APPROVAL',
            ], 200);
        }

        return DB::transaction(function () use ($opname, $userId) {
            $adjustedCount = 0;

            foreach ($opname->details as $detail) {
                $diff = (float) $detail->discrepancy;

                if (abs($diff) > 0.001) {
                    $adjustedCount++;

                    // 1. Lock baris stok di gudang yang di-opname (ACID)
                    $stock = WarehouseStock::firstOrCreate(
                        ['material_id' => $detail->material_id, 'warehouse_id' => $opname->warehouse_id],
                        ['qty_available' => 0.00, 'qty_reserved' => 0.00]
                    );

                    $stock = WarehouseStock::where('id', $stock->id)->lockForUpdate()->first();
                    $stock->qty_available = (float) $detail->physical_qty;
                    $stock->save();

                    // 2. Catat Mutasi Buku Besar (Immutable Ledger)
                    StockMutation::create([
                        'mutation_code'       => 'MUT-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                        'material_id'         => $detail->material_id,
                        'origin_warehouse_id' => $diff < 0 ? $opname->warehouse_id : null,
                        'target_warehouse_id' => $diff > 0 ? $opname->warehouse_id : null,
                        'qty'                 => abs($diff),
                        'type'                => 'OPNAME',
                        'reference_type'      => 'OPNAME_RECONCILE',
                        'reference_id'        => $opname->id,
                        'created_by'          => $userId,
                    ]);
                }
            }

            $opname->update([
                'status'       => 'COMPLETED',
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Rekonsiliasi stock opname selesai ({$adjustedCount} item disesuaikan secara fisik di buku besar).",
                'data'    => [
                    'opname_code'     => $opname->opname_code,
                    'status'          => 'COMPLETED',
                    'adjusted_count'  => $adjustedCount,
                    'completed_at'    => $opname->completed_at->toIso8601String(),
                ],
            ]);
        });
    }
}
