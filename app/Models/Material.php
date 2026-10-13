<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'item_type',     // 'material' | 'product' | 'sparepart'
        'category_id',
        'unit_id',
        'unit',          // text fallback satuan (Plano/Rim/Lembar/Kg)
        'safety_stock',
        'reorder_point',
        'supplier_id',
        'gramature',     // GSM kertas (integer)
        'dimension',     // "79 x 109 cm"
        'machine',       // Sparepart: "Heidelberg SM74"
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'safety_stock'  => 'decimal:2',
            'reorder_point' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unitRelation(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsToMany<Brand, $this>
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'material_brand');
    }

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * @return HasMany<StockMutation, $this>
     */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    /**
     * @return HasMany<BatchLot, $this>
     */
    public function batchLots(): HasMany
    {
        return $this->hasMany(BatchLot::class);
    }

    // ── Domain helpers ───────────────────────────────────────────────────

    /**
     * Check whether stock in a given warehouse is below the reorder point.
     */
    public function isBelowReorderPoint(int $warehouseId): bool
    {
        $stock = $this->warehouseStocks()->where('warehouse_id', $warehouseId)->first();

        if (! $stock) {
            return true;
        }

        return $stock->qty_available <= $this->reorder_point;
    }

    /**
     * Total available stock across all warehouses.
     */
    public function totalStock(): float
    {
        return (float) $this->warehouseStocks()->sum('qty_available');
    }
}
