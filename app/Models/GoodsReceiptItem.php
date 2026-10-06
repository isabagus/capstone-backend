<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'material_id',
        'qty_received',
        'qty_defect',
        'batch_number',
        'expiry_date',
        'humidity_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:2',
            'qty_defect' => 'decimal:2',
            'humidity_percentage' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
