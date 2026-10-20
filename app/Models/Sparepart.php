<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sparepart extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category_id',
        'unit',
        'machine',
        'supplier_id',
        'safety_stock',
        'reorder_point',
        'batch_no',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'safety_stock'  => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'expiry_date'   => 'date',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SparepartStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(SparepartStock::class);
    }
}
