<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Services\BomCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BomController extends Controller
{
    public function __construct(
        protected BomCalculatorService $calculator
    ) {}

    /**
     * POST /api/v1/bom/calculate
     * Kalkulasi instan layout plano & BOM tanpa langsung menyimpan ke DB
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plano_length'    => 'required|numeric|min:1',
            'plano_width'     => 'required|numeric|min:1',
            'cut_length'      => 'required|numeric|min:0.1',
            'cut_width'       => 'required|numeric|min:0.1',
            'target_quantity' => 'nullable|integer|min:1',
            'gsm'             => 'nullable|numeric|min:1',
            'waste_percent'   => 'nullable|numeric|min:0',
            'insheet_qty'     => 'nullable|integer|min:0',
            'mode'            => 'nullable|string|in:best,tegak,miring',
        ]);

        $result = $this->calculator->calculate($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kalkulasi BOM & Pola Potong 2D berhasil dihitung.',
            'data'    => $result,
        ]);
    }

    /**
     * GET /api/v1/bom
     * Daftar seluruh BOM yang telah disimpan
     */
    public function index(Request $request): JsonResponse
    {
        $query = BillOfMaterial::with(['material:id,sku,name,unit', 'creator:id,name']);

        if ($request->filled('order_number')) {
            $query->where('order_number', 'ilike', '%' . $request->order_number . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('bom_code', 'ilike', "%{$s}%")
                  ->orWhere('order_number', 'ilike', "%{$s}%")
                  ->orWhere('customer_name', 'ilike', "%{$s}%")
                  ->orWhere('product_name', 'ilike', "%{$s}%");
            });
        }

        $boms = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data'   => $boms->items(),
            'meta'   => [
                'current_page' => $boms->currentPage(),
                'last_page'    => $boms->lastPage(),
                'per_page'     => $boms->perPage(),
                'total'        => $boms->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/bom
     * Simpan formula kalkulasi BOM ke database PostgreSQL
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number'    => 'nullable|string|max:64',
            'customer_name'   => 'nullable|string|max:150',
            'brand'           => 'nullable|string|max:80',
            'product_name'    => 'nullable|string|max:200',
            'material_id'     => 'nullable|exists:materials,id',
            'paper_type'      => 'nullable|string|max:150',
            'paper_gsm'       => 'required|numeric|min:1',
            'plano_length_cm' => 'required|numeric|min:1',
            'plano_width_cm'  => 'required|numeric|min:1',
            'cut_length_cm'   => 'required|numeric|min:0.1',
            'cut_width_cm'    => 'required|numeric|min:0.1',
            'order_quantity'  => 'required|integer|min:1',
            'waste_percent'   => 'nullable|numeric|min:0',
            'insheet_quantity'=> 'nullable|integer|min:0',
            'mode'            => 'nullable|string|in:best,tegak,miring',
            'ink_estimate_kg' => 'nullable|numeric',
            'glue_estimate_kg'=> 'nullable|numeric',
            'foil_length_m'   => 'nullable|numeric',
            'notes'           => 'nullable|string',
        ]);

        // Hitung melalui service
        $calc = $this->calculator->calculate([
            'plano_length'    => $validated['plano_length_cm'],
            'plano_width'     => $validated['plano_width_cm'],
            'cut_length'      => $validated['cut_length_cm'],
            'cut_width'       => $validated['cut_width_cm'],
            'target_quantity' => $validated['order_quantity'],
            'gsm'             => $validated['paper_gsm'],
            'waste_percent'   => $validated['waste_percent'] ?? 5.0,
            'insheet_qty'     => $validated['insheet_quantity'] ?? 100,
            'mode'            => $validated['mode'] ?? 'best',
        ]);

        $bomCode = 'BOM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

        $bom = BillOfMaterial::create([
            'bom_code'           => $bomCode,
            'order_number'       => $validated['order_number'] ?? null,
            'customer_name'      => $validated['customer_name'] ?? null,
            'brand'              => $validated['brand'] ?? 'Packsolution.id',
            'product_name'       => $validated['product_name'] ?? null,
            'material_id'        => $validated['material_id'] ?? null,
            'paper_type'         => $validated['paper_type'] ?? 'Kertas Cetak',
            'paper_gsm'          => $validated['paper_gsm'],
            'plano_length_cm'    => $validated['plano_length_cm'],
            'plano_width_cm'     => $validated['plano_width_cm'],
            'cut_length_cm'      => $validated['cut_length_cm'],
            'cut_width_cm'       => $validated['cut_width_cm'],
            'order_quantity'     => $validated['order_quantity'],
            'cuts_per_plano'     => $calc['layout']['cuts_per_plano'],
            'orientation'        => $calc['layout']['strategy_name'],
            'layout_pieces'      => $calc['layout']['pieces'],
            'waste_percent'      => $validated['waste_percent'] ?? 5.0,
            'insheet_quantity'   => $validated['insheet_quantity'] ?? 100,
            'net_plano_sheets'   => $calc['materials_summary']['net_plano_sheets'],
            'waste_plano_sheets' => $calc['materials_summary']['waste_plano_sheets'],
            'total_plano_sheets' => $calc['materials_summary']['total_plano_sheets'],
            'total_rims'         => $calc['materials_summary']['total_rims'],
            'total_weight_kg'    => $calc['materials_summary']['total_weight_kg'],
            'efficiency_rate'    => $calc['layout']['efficiency_rate'],
            'ink_estimate_kg'    => $validated['ink_estimate_kg'] ?? $calc['consumables_estimate']['ink_offset_kg'],
            'glue_estimate_kg'   => $validated['glue_estimate_kg'] ?? $calc['consumables_estimate']['glue_kg'],
            'foil_length_m'      => $validated['foil_length_m'] ?? $calc['consumables_estimate']['foil_meter'],
            'status'             => 'CALCULATED',
            'created_by'         => auth()->id(),
            'notes'              => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Formula BOM berhasil disimpan dan siap diteruskan ke penerbitan SPK.',
            'data'    => $bom,
        ], 201);
    }

    /**
     * GET /api/v1/bom/{id}
     * Detail satu BOM
     */
    public function show(int $id): JsonResponse
    {
        $bom = BillOfMaterial::with(['material', 'creator'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $bom,
        ]);
    }

    /**
     * PUT /api/v1/bom/{id}
     * Update status atau catatan formula BOM
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bom = BillOfMaterial::findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|string|in:DRAFT,CALCULATED,APPROVED,CONVERTED_TO_SPK',
            'notes'  => 'nullable|string',
        ]);

        $bom->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status BOM berhasil diperbarui.',
            'data'    => $bom,
        ]);
    }

    /**
     * DELETE /api/v1/bom/{id}
     * Hapus formula BOM (hanya jika belum terbit SPK)
     */
    public function destroy(int $id): JsonResponse
    {
        $bom = BillOfMaterial::findOrFail($id);

        if ($bom->status === 'CONVERTED_TO_SPK') {
            return response()->json([
                'status'  => 'error',
                'message' => 'BOM tidak dapat dihapus karena sudah diterbitkan menjadi SPK Produksi.',
            ], 422);
        }

        $bom->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Formula BOM berhasil dihapus.',
        ]);
    }
}
