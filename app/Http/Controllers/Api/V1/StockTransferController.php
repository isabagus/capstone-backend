<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * List stock transfers with status and warehouse filters.
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = StockTransfer::with(['originWarehouse', 'targetWarehouse', 'createdBy'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->warehouse_id, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('origin_warehouse_id', $request->warehouse_id)
                    ->orWhere('target_warehouse_id', $request->warehouse_id);
            }))
            ->latest()
            ->paginate(20);

        return response()->json($transfers);
    }

    /**
     * Create and execute an inter-warehouse stock transfer (ACID with SELECT FOR UPDATE).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'target_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:origin_warehouse_id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $validated['created_by'] = $request->user()->id;

        $transfer = $this->inventoryService->processStockTransfer($validated);

        return response()->json($transfer, 201);
    }

    /**
     * Show a single transfer with all items.
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load(['items.material', 'originWarehouse', 'targetWarehouse', 'createdBy']);

        return response()->json($stockTransfer);
    }
}
