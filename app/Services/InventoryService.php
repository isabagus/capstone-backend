<?php

namespace App\Services;

use App\Models\BatchLot;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /**
     * Process a Goods Receipt and apply stock IN mutations.
     *
     * All stock changes and mutation ledger entries are wrapped in a single
     * DB transaction with row-level locking to prevent concurrent overwrites.
     *
     * @param  array{
     *   supplier_id: ?int,
     *   supplier_name: string,
     *   warehouse_id: int,
     *   po_number: ?string,
     *   received_date: string,
     *   received_by_user_id: int,
     *   items: array<int, array{
     *     material_id: int,
     *     qty_received: float,
     *     qty_defect: float,
     *     batch_number: ?string,
     *     expiry_date: ?string,
     *     humidity_percentage: ?float,
     *     notes: ?string
     *   }>
     * } $data
     */
    public function processGoodsReceipt(array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($data): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                'receipt_number' => $this->generateDocumentNumber('GR'),
                'po_number' => $data['po_number'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $data['supplier_name'],
                'warehouse_id' => $data['warehouse_id'],
                'received_date' => $data['received_date'],
                'received_by_user_id' => $data['received_by_user_id'],
            ]);

            foreach ($data['items'] as $item) {
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'material_id' => $item['material_id'],
                    'qty_received' => $item['qty_received'],
                    'qty_defect' => $item['qty_defect'] ?? 0,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'humidity_percentage' => $item['humidity_percentage'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);

                $acceptedQty = $item['qty_received'] - ($item['qty_defect'] ?? 0);

                if ($acceptedQty > 0) {
                    $this->incrementStock($data['warehouse_id'], $item['material_id'], $acceptedQty);

                    $this->recordMutation(
                        materialId: $item['material_id'],
                        qty: $acceptedQty,
                        type: 'IN',
                        referenceType: 'PO_INBOUND',
                        referenceId: $receipt->id,
                        targetWarehouseId: $data['warehouse_id'],
                        createdBy: $data['received_by_user_id'],
                    );

                    if (! empty($item['batch_number'])) {
                        BatchLot::create([
                            'material_id' => $item['material_id'],
                            'warehouse_id' => $data['warehouse_id'],
                            'batch_number' => $item['batch_number'],
                            'expiry_date' => $item['expiry_date'] ?? null,
                            'humidity_percentage' => $item['humidity_percentage'] ?? null,
                            'qty' => $acceptedQty,
                        ]);
                    }
                }
            }

            return $receipt->load('items.material');
        });
    }

    /**
     * Create and complete an inter-warehouse stock transfer.
     *
     * Uses SELECT FOR UPDATE on each warehouse_stocks row to prevent
     * race conditions when concurrent transfers drain the same material.
     *
     * @param  array{
     *   origin_warehouse_id: int,
     *   target_warehouse_id: int,
     *   notes: ?string,
     *   created_by: int,
     *   items: array<int, array{material_id: int, qty: float}>
     * } $data
     */
    public function processStockTransfer(array $data): StockTransfer
    {
        if ($data['origin_warehouse_id'] === $data['target_warehouse_id']) {
            throw new InvalidArgumentException('Origin and target warehouse must be different.');
        }

        return DB::transaction(function () use ($data): StockTransfer {
            $transfer = StockTransfer::create([
                'transfer_number' => $this->generateDocumentNumber('TRF'),
                'origin_warehouse_id' => $data['origin_warehouse_id'],
                'target_warehouse_id' => $data['target_warehouse_id'],
                'status' => 'IN_TRANSIT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'],
            ]);

            foreach ($data['items'] as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'material_id' => $item['material_id'],
                    'qty' => $item['qty'],
                ]);

                // Lock origin stock row before reading to prevent concurrent double-spend
                $originStock = WarehouseStock::where('warehouse_id', $data['origin_warehouse_id'])
                    ->where('material_id', $item['material_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $originStock || $originStock->qty_available < $item['qty']) {
                    throw new RuntimeException(
                        "Insufficient stock for material ID {$item['material_id']} in warehouse ID {$data['origin_warehouse_id']}."
                    );
                }

                $this->decrementStock($data['origin_warehouse_id'], $item['material_id'], $item['qty']);
                $this->incrementStock($data['target_warehouse_id'], $item['material_id'], $item['qty']);

                $this->recordMutation(
                    materialId: $item['material_id'],
                    qty: $item['qty'],
                    type: 'TRANSFER',
                    referenceType: 'WAREHOUSE_TRANSFER',
                    referenceId: $transfer->id,
                    originWarehouseId: $data['origin_warehouse_id'],
                    targetWarehouseId: $data['target_warehouse_id'],
                    createdBy: $data['created_by'],
                );
            }

            $transfer->update(['status' => 'COMPLETED']);

            return $transfer->load('items.material', 'originWarehouse', 'targetWarehouse');
        });
    }

    /**
     * Complete a stock opname session and post ADJUSTMENT mutations for discrepancies.
     *
     * @param  array{
     *   warehouse_id: int,
     *   created_by: int,
     *   details: array<int, array{
     *     material_id: int,
     *     system_qty: float,
     *     physical_qty: float,
     *     notes: ?string
     *   }>
     * } $data
     */
    public function processStockOpname(array $data): StockOpname
    {
        return DB::transaction(function () use ($data): StockOpname {
            $opname = StockOpname::create([
                'opname_code' => $this->generateDocumentNumber('OPN'),
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'PENDING_APPROVAL',
                'created_by' => $data['created_by'],
            ]);

            foreach ($data['details'] as $detail) {
                StockOpnameDetail::create([
                    'opname_id' => $opname->id,
                    'material_id' => $detail['material_id'],
                    'system_qty' => $detail['system_qty'],
                    'physical_qty' => $detail['physical_qty'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }

            return $opname->load('details.material');
        });
    }

    /**
     * Approve a pending opname and reconcile warehouse stock balances.
     */
    public function approveStockOpname(StockOpname $opname, int $approvedByUserId): StockOpname
    {
        if ($opname->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only opnames in PENDING_APPROVAL status can be approved.');
        }

        return DB::transaction(function () use ($opname, $approvedByUserId): StockOpname {
            foreach ($opname->details as $detail) {
                $discrepancy = $detail->physical_qty - $detail->system_qty;

                if (abs($discrepancy) < 0.001) {
                    continue;
                }

                // Lock the stock row before adjusting
                WarehouseStock::where('warehouse_id', $opname->warehouse_id)
                    ->where('material_id', $detail->material_id)
                    ->lockForUpdate()
                    ->first();

                if ($discrepancy > 0) {
                    $this->incrementStock($opname->warehouse_id, $detail->material_id, abs($discrepancy));
                } else {
                    $this->decrementStock($opname->warehouse_id, $detail->material_id, abs($discrepancy));
                }

                $this->recordMutation(
                    materialId: $detail->material_id,
                    qty: abs($discrepancy),
                    type: $discrepancy > 0 ? 'IN' : 'OUT',
                    referenceType: 'OPNAME_ADJUSTMENT',
                    referenceId: $opname->id,
                    targetWarehouseId: $discrepancy > 0 ? $opname->warehouse_id : null,
                    originWarehouseId: $discrepancy < 0 ? $opname->warehouse_id : null,
                    createdBy: $approvedByUserId,
                );
            }

            $opname->update(['status' => 'COMPLETED']);

            return $opname->refresh();
        });
    }

    /**
     * Increment (or upsert) a warehouse stock row by the given qty.
     * Must be called inside an active DB::transaction.
     */
    private function incrementStock(int $warehouseId, int $materialId, float $qty): void
    {
        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'material_id' => $materialId],
            ['qty_available' => 0, 'qty_reserved' => 0],
        );

        WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('material_id', $materialId)
            ->increment('qty_available', $qty);
    }

    /**
     * Decrement a warehouse stock row by the given qty.
     * Must be called inside an active DB::transaction.
     */
    private function decrementStock(int $warehouseId, int $materialId, float $qty): void
    {
        WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('material_id', $materialId)
            ->decrement('qty_available', $qty);
    }

    /**
     * Append an immutable ledger entry to stock_mutations.
     */
    private function recordMutation(
        int $materialId,
        float $qty,
        string $type,
        string $referenceType,
        int $referenceId,
        int $createdBy,
        ?int $originWarehouseId = null,
        ?int $targetWarehouseId = null,
    ): void {
        StockMutation::create([
            'mutation_code' => $this->generateDocumentNumber('MUT'),
            'material_id' => $materialId,
            'origin_warehouse_id' => $originWarehouseId,
            'target_warehouse_id' => $targetWarehouseId,
            'qty' => $qty,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Generate a sequential document number with prefix and YYYYMM segment.
     * Example: GR-202609-001
     */
    private function generateDocumentNumber(string $prefix): string
    {
        $yearMonth = now()->format('Ym');
        $lastNumber = StockMutation::where('mutation_code', 'like', "{$prefix}-{$yearMonth}-%")
            ->count();

        return sprintf('%s-%s-%03d', $prefix, $yearMonth, $lastNumber + 1);
    }
}
