<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparepartStock extends Model
{
    protected $fillable = [
        'sparepart_id',
        'warehouse_id',
        'qty_available',
        'qty_reserved',
    ];

    protected function casts(): array
    {
        return [
            'qty_available' => 'decimal:2',
            'qty_reserved'  => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sparepart, $this>
     */
    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
