<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spk extends Model
{
    use HasFactory;

    protected $table = 'spks';

    protected $fillable = [
        'spk_number',
        'order_number',
        'folder_name',
        'job_name',
        'customer_name',
        'brand_id',
        'bom_id',
        'type',
        'vendor_id',
        'product_type_label',
        'delivery_type',
        'receipt_number',
        'color_standard',
        'packing_type',
        'order_quantity',
        'publish_date',
        'deadline',
        'status',
        'finishing_details',
        'cost_breakdown',
        'notes',
        'layouter_id',
        'checker1_id',
        'checker2_id',
        'created_by',
    ];

    protected $casts = [
        'finishing_details' => 'array',
        'cost_breakdown'    => 'array',
        'publish_date'      => 'date:Y-m-d',
        'deadline'          => 'datetime',
        'order_quantity'    => 'integer',
    ];

    /**
     * Relasi ke Detail Kebutuhan Multi-Bahan Baku
     */
    public function materials(): HasMany
    {
        return $this->hasMany(SpkMaterial::class, 'spk_id');
    }

    /**
     * Relasi ke Brand Pemesan
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relasi ke Bill of Material (BOM) Asal
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    /**
     * Relasi ke Vendor Mitra (Jika SPK Eksternal / Maklon)
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Relasi Pengesahan Dokumen (Layouter & Checkers)
     */
    public function layouter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'layouter_id');
    }

    public function checker1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker1_id');
    }

    public function checker2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker2_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
