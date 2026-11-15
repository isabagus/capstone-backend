<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillOfMaterial extends Model
{
    use HasFactory;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'bom_code',
        'order_number',
        'customer_name',
        'brand',
        'product_name',
        'material_id',
        'paper_type',
        'paper_gsm',
        'plano_length_cm',
        'plano_width_cm',
        'cut_length_cm',
        'cut_width_cm',
        'order_quantity',
        'cuts_per_plano',
        'orientation',
        'layout_pieces',
        'waste_percent',
        'insheet_quantity',
        'net_plano_sheets',
        'waste_plano_sheets',
        'total_plano_sheets',
        'total_rims',
        'total_weight_kg',
        'efficiency_rate',
        'ink_estimate_kg',
        'glue_estimate_kg',
        'foil_length_m',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'paper_gsm'          => 'float',
        'plano_length_cm'    => 'float',
        'plano_width_cm'     => 'float',
        'cut_length_cm'      => 'float',
        'cut_width_cm'       => 'float',
        'order_quantity'     => 'integer',
        'cuts_per_plano'     => 'integer',
        'layout_pieces'      => 'array',
        'waste_percent'      => 'float',
        'insheet_quantity'   => 'integer',
        'net_plano_sheets'   => 'integer',
        'waste_plano_sheets' => 'integer',
        'total_plano_sheets' => 'integer',
        'total_rims'         => 'float',
        'total_weight_kg'    => 'float',
        'efficiency_rate'    => 'float',
        'ink_estimate_kg'    => 'float',
        'glue_estimate_kg'   => 'float',
        'foil_length_m'      => 'float',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
