<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * List goods receipts with optional warehouse and date filters.
     */
    public function index(Request $request): JsonResponse
    {
        $receipts = GoodsReceipt::with(['warehouse', 'receivedBy', 'supplier'])
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('received_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('received_date', '<=', $request->date_to))
            ->latest('received_date')
            ->paginate(20);

        return response()->json($receipts);
    }

    /**
     * Create and process a goods receipt (stock IN + mutation ledger).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['required', 'string', 'max:100'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'po_number' => ['nullable', 'string', 'max:50'],
            'received_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.qty_received' => ['required', 'numeric', 'min:0.01'],
            'items.*.qty_defect' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.humidity_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $validated['received_by_user_id'] = $request->user()->id;

        $receipt = $this->inventoryService->processGoodsReceipt($validated);

        return response()->json($receipt, 201);
    }

    /**
     * Show a single goods receipt with all items.
     */
    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $goodsReceipt->load(['items.material.category', 'warehouse', 'receivedBy', 'supplier']);

        return response()->json($goodsReceipt);
    }
}
