<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkMaterial extends Model
{
    use HasFactory;

    protected $table = 'spk_materials';

    protected $fillable = [
        'spk_id',
        'material_id',
        'source_type',
        'material_name',
        'component_name',
        'plano_sheets',
        'cuts_per_plano',
        'cut_sheets',
        'cut_size',
        'file_size',
        'target_product',
        'cutting_layout_schema',
        'total_quantity',
        'unit',
        'source_warehouse_id',
    ];

    protected $casts = [
        'plano_sheets'          => 'integer',
        'cuts_per_plano'        => 'integer',
        'cut_sheets'            => 'integer',
        'total_quantity'        => 'decimal:2',
        'cutting_layout_schema' => 'array',
    ];

    /**
     * Relasi balik ke Dokumen SPK Header
     */
    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    /**
     * Relasi ke Master Bahan Baku di Gudang
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Relasi ke Gudang Asal Pengeluaran Bahan
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }
}
