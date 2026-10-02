<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     * GET /api/v1/suppliers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $suppliers = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar supplier berhasil diambil',
            'data'    => $suppliers,
            'meta'    => [
                'total' => $suppliers->count(),
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     * POST /api/v1/suppliers
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:20', 'unique:suppliers,code'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email', 'max:100'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $supplier = Supplier::create([
            'name'      => trim($validated['name']),
            'code'      => strtoupper(trim($validated['code'])),
            'phone'     => isset($validated['phone']) ? trim($validated['phone']) : null,
            'email'     => isset($validated['email']) ? trim($validated['email']) : null,
            'address'   => isset($validated['address']) ? trim($validated['address']) : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier baru berhasil ditambahkan',
            'data'    => $supplier,
        ], 201);
    }

    /**
     * Display the specified supplier.
     * GET /api/v1/suppliers/{id}
     */
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::with('goodsReceipts')->find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => "Supplier dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail supplier berhasil diambil',
            'data'    => $supplier,
        ]);
    }

    /**
     * Update the specified supplier.
     * PUT/PATCH /api/v1/suppliers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => "Supplier dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:100'],
            'code'      => ['sometimes', 'required', 'string', 'max:20', Rule::unique('suppliers', 'code')->ignore($supplier->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email', 'max:100'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $supplier->update([
            'name'      => isset($validated['name']) ? trim($validated['name']) : $supplier->name,
            'code'      => isset($validated['code']) ? strtoupper(trim($validated['code'])) : $supplier->code,
            'phone'     => array_key_exists('phone', $validated) ? trim($validated['phone']) : $supplier->phone,
            'email'     => array_key_exists('email', $validated) ? trim($validated['email']) : $supplier->email,
            'address'   => array_key_exists('address', $validated) ? trim($validated['address']) : $supplier->address,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $supplier->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil diperbarui',
            'data'    => $supplier,
        ]);
    }

    /**
     * Remove the specified supplier.
     * DELETE /api/v1/suppliers/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => "Supplier dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        // Soft check if supplier has goods receipts
        if ($supplier->goodsReceipts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier tidak dapat dihapus karena memiliki riwayat penerimaan barang (Goods Receipts). Non-aktifkan supplier sebagai gantinya.',
                'error_code' => 'SUPPLIER_HAS_TRANSACTIONS',
            ], 400);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier berhasil dihapus',
        ]);
    }
}
