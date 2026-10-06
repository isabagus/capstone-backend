<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    protected $fillable = [
        'material_id',
        'warehouse_id',
        'qty_available',
        'qty_reserved',
    ];

    protected function casts(): array
    {
        return [
            'qty_available' => 'decimal:2',
            'qty_reserved' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Net quantity that can actually be allocated (available minus reserved).
     */
    public function qtyAllocatable(): float
    {
        return max(0, (float) $this->qty_available - (float) $this->qty_reserved);
    }
}
