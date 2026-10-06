<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\WarehouseStock;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * List opname sessions with status and warehouse filters.
     */
    public function index(Request $request): JsonResponse
    {
        $opnames = StockOpname::with(['warehouse', 'createdBy'])
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($opnames);
    }

    /**
     * Create a new stock opname session (status: PENDING_APPROVAL).
     * System qty is auto-filled from current warehouse_stocks.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'details.*.physical_qty' => ['required', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
        ]);

        // Auto-fill system_qty from current warehouse stock for each item
        foreach ($validated['details'] as &$detail) {
            $stock = WarehouseStock::where('warehouse_id', $validated['warehouse_id'])
                ->where('material_id', $detail['material_id'])
                ->first();

            $detail['system_qty'] = $stock ? (float) $stock->qty_available : 0.00;
        }
        unset($detail);

        $validated['created_by'] = $request->user()->id;

        $opname = $this->inventoryService->processStockOpname($validated);

        return response()->json($opname, 201);
    }

    /**
     * Show a single opname with details.
     */
    public function show(StockOpname $stockOpname): JsonResponse
    {
        $stockOpname->load(['details.material', 'warehouse', 'createdBy']);

        return response()->json($stockOpname);
    }

    /**
     * Approve an opname: reconcile warehouse stock balances and post ADJUSTMENT mutations.
     * Only Manager or Owner can approve.
     */
    public function approve(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $opname = $this->inventoryService->approveStockOpname($stockOpname, $request->user()->id);

        return response()->json([
            'message' => 'Stock opname approved and stock balances reconciled.',
            'opname' => $opname,
        ]);
    }
}
