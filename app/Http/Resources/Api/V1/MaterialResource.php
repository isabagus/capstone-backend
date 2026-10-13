<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MaterialResource
 *
 * Transforms the Material Eloquent model into the shape expected by the
 * Next.js frontend (types/inventory.ts → Material | Product | Sparepart).
 *
 * Field mapping summary:
 *   DB column          → Frontend field
 *   sku                → code        (Materials / Spareparts use "code" key)
 *   reorder_point      → rop
 *   safety_stock       → safetyStock
 *   category_id        → categoryId
 *   unit_id            → unitId
 *   supplier_id        → supplierId
 *   warehouseStocks[]  → stock: { <warehouseCode>: qty }
 *   brands[]           → brands: string[]
 *
 * @mixin \App\Models\Material
 */
class MaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Build the stock map: { warehouseCode → qty_available }
        // Frontend uses "g1" / "g2" keys derived from the warehouse code field.
        // We also expose warehouse_id as fallback key so the frontend can
        // resolve by ID when needed.
        $stockMap = $this->buildStockMap();

        // Latest batch lot for this material (most recently received)
        $latestBatch = $this->batchLots->sortByDesc('id')->first();

        return [
            // ── Identity ────────────────────────────────────────────
            'id'         => (string) $this->id,

            /**
             * "code" is the canonical key used by Materials & Spareparts pages.
             * Products page uses "sku" — both are served here so either page
             * can consume this resource without adapter gymnastics.
             */
            'code' => $this->sku,
            'sku'  => $this->sku,

            'name'     => $this->name,
            'itemType' => $this->item_type ?? 'material',

            // ── Categorisation ──────────────────────────────────────
            'categoryId'   => $this->category_id ? (string) $this->category_id : null,
            'categoryName' => $this->whenLoaded('category', fn () => $this->category?->name),

            // ── Brands (array of brand names for multi-brand materials) ──
            'brands' => $this->whenLoaded(
                'brands',
                fn () => $this->brands->pluck('name')->values()->all(),
                []
            ),

            // ── Unit ────────────────────────────────────────────────
            'unitId'   => $this->unit_id ? (string) $this->unit_id : null,
            'unitCode' => $this->unit,                              // Plano/Rim/Lembar/Kg
            'unitName' => $this->whenLoaded('unitRelation', fn () => $this->unitRelation?->name),
            'unitAbbr' => $this->whenLoaded('unitRelation', fn () => $this->unitRelation?->code),

            // ── Stock per warehouse ──────────────────────────────────
            /**
             * stock: { "g1": 45.00, "g2": 0.00, ... }
             * The frontend StockByWarehouse type uses string warehouse codes.
             * The warehouse "code" slug (g1/g2) is derived from the seeder.
             * Raw warehouse IDs are also embedded for completeness.
             */
            'stock'        => $stockMap,
            'totalStock'   => array_sum($stockMap),
            'qtyReserved'  => $this->whenLoaded(
                'warehouseStocks',
                fn () => (float) $this->warehouseStocks->sum('qty_reserved')
            ),

            // ── Reorder thresholds ──────────────────────────────────
            'rop'         => (float) $this->reorder_point,
            'safetyStock' => (float) $this->safety_stock,

            // ── Supplier ────────────────────────────────────────────
            'supplierId'   => $this->supplier_id ? (string) $this->supplier_id : null,
            'supplierName' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),

            // ── Paper / print-specific specs ────────────────────────
            'gramature' => $this->gramature,       // GSM integer
            'dimension' => $this->dimension,       // "79 x 109 cm"

            // ── Sparepart-specific ───────────────────────────────────
            'machine' => $this->machine,

            // ── Batch / lot tracking ─────────────────────────────────
            'batchNo'    => $latestBatch?->batch_number,
            'expiryDate' => $latestBatch?->expiry_date?->toDateString(),
            'humidity'   => $latestBatch ? (float) $latestBatch->humidity_percentage : null,

            // ── Product-specific (minStock alias = safetyStock) ──────
            'minStock' => (float) $this->safety_stock,

            // ── Timestamps ──────────────────────────────────────────
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Build the { warehouseCode → qty_available } stock map.
     *
     * Priority: use the warehouse's own code column when available,
     * otherwise fall back to a generated slug: g{id}.
     *
     * @return array<string, float>
     */
    private function buildStockMap(): array
    {
        if (! $this->relationLoaded('warehouseStocks')) {
            return [];
        }

        $map = [];

        foreach ($this->warehouseStocks as $ws) {
            // Use the code if the warehouse relation was loaded, else g{id}
            $key = $ws->relationLoaded('warehouse') && $ws->warehouse
                ? $this->warehouseKey($ws->warehouse)
                : 'g'.$ws->warehouse_id;

            $map[$key] = (float) $ws->qty_available;
        }

        return $map;
    }

    /**
     * Derive a short frontend-friendly key for a warehouse.
     * "Gudang 1 Utama" → "g1", "Gudang 2 Ruko" → "g2", etc.
     *
     * @param  \App\Models\Warehouse  $warehouse
     */
    private function warehouseKey(Warehouse $warehouse): string
    {
        // If the warehouse has a "code" column (e.g. WH-1), use it
        // otherwise build g{id}
        return 'g'.$warehouse->id;
    }
}
