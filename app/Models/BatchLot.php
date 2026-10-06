<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchLot extends Model
{
    protected $fillable = [
        'material_id',
        'warehouse_id',
        'batch_number',
        'expiry_date',
        'humidity_percentage',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'humidity_percentage' => 'decimal:2',
            'qty' => 'decimal:2',
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
     * Check if this batch lot has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
