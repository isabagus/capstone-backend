<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchLot;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockMutationController extends Controller
{
    /**
     * Resolusi ID Gudang dari input yang fleksibel ("g1", "g2", "1", "2", atau nama/kode)
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

        $w = Warehouse::where('code', $value)->orWhere('name', 'like', "%{$value}%")->first();
        return $w ? $w->id : 1;
    }

    /**
     * Resolusi ID Material dari input ("m-1", "1", SKU, dsb)
     */
    protected function resolveMaterial(mixed $value): ?Material
    {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            $m = Material::find((int) $value);
            if ($m) return $m;
        }

        $cleanId = preg_replace('/^[a-z]+-/i', '', (string) $value);
        if (is_numeric($cleanId)) {
            $m = Material::find((int) $cleanId);
            if ($m) return $m;
        }

        $m = Material::where('sku', $value)->orWhere('name', $value)->first();
        if ($m) return $m;

        return Material::first();
    }

    /**
     * GET /api/v1/inventory/movements
     * Menampilkan riwayat buku besar mutasi stok dari database PostgreSQL.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockMutation::with([
            'material:id,sku,name,unit',
            'originWarehouse:id,name,code',
            'targetWarehouse:id,name,code',
            'createdBy:id,name,email',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $type = strtoupper($request->input('type'));
            if ($type === 'OPNAME') {
                $query->whereIn('type', ['OPNAME', 'ADJUSTMENT']);
            } else {
                $query->where('type', $type);
            }
        }

        if ($request->filled('material_id')) {
            $mat = $this->resolveMaterial($request->input('material_id'));
            if ($mat) {
                $query->where('material_id', $mat->id);
            }
        }

        if ($request->filled('warehouse_id') && $request->input('warehouse_id') !== 'all') {
            $wId = $this->resolveWarehouseId($request->input('warehouse_id'));
            $query->where(function ($q) use ($wId) {
                $q->where('origin_warehouse_id', $wId)
                  ->orWhere('target_warehouse_id', $wId);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('mutation_code', 'like', "%{$search}%")
                  ->orWhere('reference_type', 'like', "%{$search}%")
                  ->orWhereHas('material', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $limit = $request->input('limit', 50);
        $mutations = $query->take($limit)->get();

        $data = $mutations->map(function ($m) {
            $type = $m->type;
            if ($type === 'ADJUSTMENT') $type = 'OPNAME';

            $wOriginId = $m->origin_warehouse_id;
            $wTargetId = $m->target_warehouse_id;

            $primaryWarehouse = $type === 'OUT' || $type === 'TRANSFER'
                ? ($wOriginId == 1 ? 'g1' : 'g2')
                : ($wTargetId == 1 ? 'g1' : ($wTargetId == 2 ? 'g2' : 'g1'));

            $targetWarehouse = $wTargetId ? ($wTargetId == 1 ? 'g1' : 'g2') : null;

            return [
                'id'            => 'mut-' . $m->id,
                'mutation_code' => $m->mutation_code,
                'itemKind'      => 'material',
                'itemId'        => (string) $m->material_id,
                'itemName'      => $m->material?->name ?? 'Material #' . $m->material_id,
                'itemSku'       => $m->material?->sku ?? '-',
                'unit'          => $m->material?->unit ?? 'Rim',
                'type'          => $type,
                'warehouse'     => $primaryWarehouse,
                'toWarehouse'   => $targetWarehouse,
                'originName'    => $m->originWarehouse?->name,
                'targetName'    => $m->targetWarehouse?->name,
                'qty'           => (float) $m->qty,
                'reference'     => $m->reference_type . ' #' . $m->reference_id,
                'reference_type'=> $m->reference_type,
                'reference_id'  => (string) $m->reference_id,
                'user'          => $m->createdBy?->name ?? 'Staf Gudang',
                'date'          => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat mutasi stok berhasil dimuat',
            'data'    => $data,
            'meta'    => [
                'total' => $mutations->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/inventory/in
     * Mencatat penerimaan barang masuk dari PO Supplier (Inbound Material).
     */
    public function stockIn(Request $request): JsonResponse
    {
        $request->validate([
            'material_id'  => 'required',
            'warehouse_id' => 'required',
            'qty'          => 'required|numeric|gt:0',
            'reference'    => 'nullable|string|max:100',
            'batch_no'     => 'nullable|string|max:100',
            'expiry_date'  => 'nullable|date',
            'note'         => 'nullable|string|max:255',
        ]);

        $material = $this->resolveMaterial($request->input('material_id'));
        if (!$material) {
            return response()->json(['success' => false, 'message' => 'Material tidak ditemukan.'], 404);
        }

        $warehouseId = $this->resolveWarehouseId($request->input('warehouse_id'));
        $qty = (float) $request->input('qty');
        $userId = auth('sanctum')->id() ?? 1;

        return DB::transaction(function () use ($request, $material, $warehouseId, $qty, $userId) {
            // 1. Lock baris stok di gudang tujuan (ACID)
            $stock = WarehouseStock::where('material_id', $material->id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = WarehouseStock::create([
                    'material_id'   => $material->id,
                    'warehouse_id'  => $warehouseId,
                    'qty_available' => 0.00,
                    'qty_reserved'  => 0.00,
                ]);
            }

            // 2. Tambah stok fisik
            $stock->qty_available = (float) $stock->qty_available + $qty;
            $stock->save();

            // 3. Catat Batch / Lot jika ada
            if ($request->filled('batch_no')) {
                BatchLot::create([
                    'batch_number'        => $request->input('batch_no'),
                    'material_id'         => $material->id,
                    'warehouse_id'        => $warehouseId,
                    'qty'                 => $qty,
                    'expiry_date'         => $request->input('expiry_date'),
                    'humidity_percentage' => (float) $request->input('humidity_percentage', 5.0),
                ]);
            }

            // 4. Catat Goods Receipt
            $gr = GoodsReceipt::create([
                'receipt_number'      => 'GR-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'po_number'           => $request->input('reference', 'PO-' . date('Ymd')),
                'supplier_id'         => $material->supplier_id ?? null,
                'supplier_name'       => $material->supplier?->name ?? 'Supplier Mitra',
                'warehouse_id'        => $warehouseId,
                'received_date'       => now(),
                'received_by_user_id' => $userId,
            ]);

            GoodsReceiptItem::create([
                'goods_receipt_id' => $gr->id,
                'material_id'      => $material->id,
                'qty_received'     => $qty,
                'batch_number'     => $request->input('batch_no'),
                'expiration_date'  => $request->input('expiry_date'),
            ]);

            // 5. Catat Mutasi Buku Besar (Immutable Ledger)
            $mutation = StockMutation::create([
                'mutation_code'       => 'MUT-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'material_id'         => $material->id,
                'origin_warehouse_id' => null,
                'target_warehouse_id' => $warehouseId,
                'qty'                 => $qty,
                'type'                => 'IN',
                'reference_type'      => 'PO_INBOUND',
                'reference_id'        => $gr->id,
                'created_by'          => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Barang masuk berhasil dicatat. Stok {$material->name} bertambah {$qty} {$material->unit}.",
                'data'    => [
                    'mutation_id'    => $mutation->id,
                    'mutation_code'  => $mutation->mutation_code,
                    'current_stock'  => (float) $stock->qty_available,
                ],
            ], 201);
        });
    }

    /**
     * POST /api/v1/inventory/out
     * Mencatat pengeluaran barang untuk SPK Produksi / pengiriman.
     */
    public function stockOut(Request $request): JsonResponse
    {
        $request->validate([
            'material_id'  => 'required',
            'warehouse_id' => 'required',
            'qty'          => 'required|numeric|gt:0',
            'reference'    => 'nullable|string|max:100',
            'note'         => 'nullable|string|max:255',
        ]);

        $material = $this->resolveMaterial($request->input('material_id'));
        if (!$material) {
            return response()->json(['success' => false, 'message' => 'Material tidak ditemukan.'], 404);
        }

        $warehouseId = $this->resolveWarehouseId($request->input('warehouse_id'));
        $qty = (float) $request->input('qty');
        $userId = auth('sanctum')->id() ?? 1;

        return DB::transaction(function () use ($request, $material, $warehouseId, $qty, $userId) {
            // 1. Lock baris stok di gudang asal (ACID)
            $stock = WarehouseStock::where('material_id', $material->id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $available = $stock ? (float) $stock->qty_available : 0.0;
            if ($available < $qty) {
                return response()->json([
                    'success' => false,
                    'message' => "Stok di gudang tidak mencukupi (Tersedia: {$available} {$material->unit}, Diminta: {$qty} {$material->unit}).",
                ], 422);
            }

            // 2. Kurangi stok
            $stock->qty_available = $available - $qty;
            $stock->save();

            // 3. Catat Mutasi Buku Besar (Immutable Ledger)
            $mutation = StockMutation::create([
                'mutation_code'       => 'MUT-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'material_id'         => $material->id,
                'origin_warehouse_id' => $warehouseId,
                'target_warehouse_id' => null,
                'qty'                 => $qty,
                'type'                => 'OUT',
                'reference_type'      => 'SPK_OUTBOUND',
                'reference_id'        => (int) ($request->input('spk_id') ?? 1),
                'created_by'          => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Pengeluaran barang berhasil. Sisa stok {$material->name}: {$stock->qty_available} {$material->unit}.",
                'data'    => [
                    'mutation_id'    => $mutation->id,
                    'mutation_code'  => $mutation->mutation_code,
                    'current_stock'  => (float) $stock->qty_available,
                ],
            ]);
        });
    }

    /**
     * POST /api/v1/inventory/transfer
     * Transfer stok antar gudang G1 ⇄ G2 secara ACID dengan row-locking.
     */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'material_id'         => 'required',
            'origin_warehouse_id' => 'required',
            'target_warehouse_id' => 'required',
            'qty'                 => 'required|numeric|gt:0',
            'note'                => 'nullable|string|max:255',
        ]);

        $material = $this->resolveMaterial($request->input('material_id'));
        if (!$material) {
            return response()->json(['success' => false, 'message' => 'Material tidak ditemukan.'], 404);
        }

        $originId = $this->resolveWarehouseId($request->input('origin_warehouse_id'));
        $targetId = $this->resolveWarehouseId($request->input('target_warehouse_id'));

        if ($originId === $targetId) {
            return response()->json([
                'success' => false,
                'message' => 'Gudang asal dan gudang tujuan tidak boleh sama.',
            ], 422);
        }

        $qty = (float) $request->input('qty');
        $userId = auth('sanctum')->id() ?? 1;

        return DB::transaction(function () use ($request, $material, $originId, $targetId, $qty, $userId) {
            // Deadlock prevention: kunci baris dengan urutan ID terkecil dahulu
            $firstLockId = min($originId, $targetId);
            $secondLockId = max($originId, $targetId);

            // Inisialisasi slot jika belum ada
            foreach ([$originId, $targetId] as $wId) {
                WarehouseStock::firstOrCreate(
                    ['material_id' => $material->id, 'warehouse_id' => $wId],
                    ['qty_available' => 0.00, 'qty_reserved' => 0.00]
                );
            }

            // Lock kedua gudang secara berurutan
            WarehouseStock::where('material_id', $material->id)
                ->whereIn('warehouse_id', [$firstLockId, $secondLockId])
                ->orderBy('warehouse_id', 'asc')
                ->lockForUpdate()
                ->get();

            $originStock = WarehouseStock::where('material_id', $material->id)
                ->where('warehouse_id', $originId)
                ->first();

            $targetStock = WarehouseStock::where('material_id', $material->id)
                ->where('warehouse_id', $targetId)
                ->first();

            $originAvailable = (float) $originStock->qty_available;
            if ($originAvailable < $qty) {
                return response()->json([
                    'success' => false,
                    'message' => "Stok gudang asal tidak mencukupi (Tersedia: {$originAvailable} {$material->unit}, Transfer: {$qty} {$material->unit}).",
                ], 422);
            }

            // Pindahkan kuantitas secara atomik
            $originStock->qty_available = $originAvailable - $qty;
            $originStock->save();

            $targetStock->qty_available = (float) $targetStock->qty_available + $qty;
            $targetStock->save();

            // Catat dokumen Stock Transfer
            $transferDoc = StockTransfer::create([
                'transfer_number'     => 'TRF-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'origin_warehouse_id' => $originId,
                'target_warehouse_id' => $targetId,
                'status'              => 'COMPLETED',
                'notes'               => $request->input('note', 'Transfer fisik logistik antar-gudang'),
                'created_by'          => $userId,
            ]);

            StockTransferItem::create([
                'stock_transfer_id' => $transferDoc->id,
                'material_id'       => $material->id,
                'qty'               => $qty,
            ]);

            // Catat Mutasi Buku Besar (Immutable Ledger)
            $mutation = StockMutation::create([
                'mutation_code'       => 'MUT-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'material_id'         => $material->id,
                'origin_warehouse_id' => $originId,
                'target_warehouse_id' => $targetId,
                'qty'                 => $qty,
                'type'                => 'TRANSFER',
                'reference_type'      => 'WAREHOUSE_TRANSFER',
                'reference_id'        => $transferDoc->id,
                'created_by'          => $userId,
            ]);

            $wOriginName = Warehouse::find($originId)?->name ?? 'Gudang 1';
            $wTargetName = Warehouse::find($targetId)?->name ?? 'Gudang 2';

            return response()->json([
                'success' => true,
                'message' => "Transfer {$qty} {$material->unit} dari {$wOriginName} ke {$wTargetName} berhasil (ACID Completed).",
                'data'    => [
                    'mutation_id'    => $mutation->id,
                    'mutation_code'  => $mutation->mutation_code,
                    'origin_stock'   => (float) $originStock->qty_available,
                    'target_stock'   => (float) $targetStock->qty_available,
                ],
            ]);
        });
    }

    /**
     * POST /api/v1/inventory/opname
     * Penyesuaian stok fisik aktual hasil stock opname di lapangan.
     */
    public function stockOpname(Request $request): JsonResponse
    {
        $request->validate([
            'material_id'  => 'required',
            'warehouse_id' => 'required',
            'physical_qty' => 'required|numeric|gte:0',
            'note'         => 'nullable|string|max:255',
        ]);

        $material = $this->resolveMaterial($request->input('material_id'));
        if (!$material) {
            return response()->json(['success' => false, 'message' => 'Material tidak ditemukan.'], 404);
        }

        $warehouseId = $this->resolveWarehouseId($request->input('warehouse_id'));
        $physicalQty = (float) $request->input('physical_qty');
        $userId = auth('sanctum')->id() ?? 1;

        return DB::transaction(function () use ($request, $material, $warehouseId, $physicalQty, $userId) {
            $stock = WarehouseStock::firstOrCreate(
                ['material_id' => $material->id, 'warehouse_id' => $warehouseId],
                ['qty_available' => 0.00, 'qty_reserved' => 0.00]
            );

            // Lock row
            $stock = WarehouseStock::where('id', $stock->id)->lockForUpdate()->first();
            $systemQty = (float) $stock->qty_available;
            $diff = $physicalQty - $systemQty;

            $stock->qty_available = $physicalQty;
            $stock->save();

            // Catat opname
            $opname = StockOpname::create([
                'opname_code'  => 'OPN-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'warehouse_id' => $warehouseId,
                'status'       => 'COMPLETED',
                'created_by'   => $userId,
            ]);

            StockOpnameDetail::create([
                'opname_id'       => $opname->id,
                'material_id'     => $material->id,
                'system_qty'      => $systemQty,
                'physical_qty'    => $physicalQty,
                'notes'           => $request->input('note', 'Stock opname fisik gudang'),
            ]);

            // Catat mutasi buku besar
            $mutation = StockMutation::create([
                'mutation_code'       => 'MUT-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
                'material_id'         => $material->id,
                'origin_warehouse_id' => $diff < 0 ? $warehouseId : null,
                'target_warehouse_id' => $diff > 0 ? $warehouseId : null,
                'qty'                 => abs($diff),
                'type'                => 'OPNAME',
                'reference_type'      => 'OPNAME_ADJUSTMENT',
                'reference_id'        => $opname->id,
                'created_by'          => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stock opname berhasil disimpan. Saldo {$material->name} disesuaikan menjadi {$physicalQty} {$material->unit} (Selisih: {$diff}).",
                'data'    => [
                    'mutation_id'    => $mutation->id,
                    'difference'     => $diff,
                    'current_stock'  => $physicalQty,
                ],
            ]);
        });
    }
}
