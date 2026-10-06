<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMutationController extends Controller
{
    /**
     * List the immutable mutation ledger with rich filters.
     * Read-only — no store/update/destroy allowed.
     */
    public function index(Request $request): JsonResponse
    {
        $mutations = StockMutation::with(['material', 'originWarehouse', 'targetWarehouse', 'createdBy'])
            ->when($request->material_id, fn ($q) => $q->where('material_id', $request->material_id))
            ->when($request->warehouse_id, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('origin_warehouse_id', $request->warehouse_id)
                    ->orWhere('target_warehouse_id', $request->warehouse_id);
            }))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->reference_type, fn ($q) => $q->where('reference_type', $request->reference_type))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($mutations);
    }

    /**
     * Show a single mutation ledger entry.
     */
    public function show(StockMutation $stockMutation): JsonResponse
    {
        $stockMutation->load(['material', 'originWarehouse', 'targetWarehouse', 'createdBy']);

        return response()->json($stockMutation);
    }
}
