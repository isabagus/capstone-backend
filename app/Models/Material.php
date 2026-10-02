<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'unit',
        'safety_stock',
        'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'safety_stock' => 'decimal:2',
            'reorder_point' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
}
