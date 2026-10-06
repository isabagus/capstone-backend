<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['name', 'address', 'type'];

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function outboundTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'origin_warehouse_id');
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function inboundTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'target_warehouse_id');
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
