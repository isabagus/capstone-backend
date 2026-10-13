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

        $data = $suppliers->map(function ($s) {
            return [
                'id'        => (string) $s->id,
                'name'      => $s->name,
                'code'      => $s->code,
                'phone'     => $s->phone ?? '',
                'email'     => $s->email ?? '',
                'address'   => $s->address ?? '',
                'active'    => (bool) $s->is_active,
                'is_active' => (bool) $s->is_active,
                'materials' => [],
                'created_at'=> $s->created_at?->toIso8601String(),
                'updated_at'=> $s->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar supplier berhasil diambil',
            'data'    => $data,
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
            'active'    => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isActive = $validated['active'] ?? ($validated['is_active'] ?? true);

        $supplier = Supplier::create([
            'name'      => trim($validated['name']),
            'code'      => strtoupper(trim($validated['code'])),
            'phone'     => isset($validated['phone']) ? trim($validated['phone']) : null,
            'email'     => isset($validated['email']) ? trim($validated['email']) : null,
            'address'   => isset($validated['address']) ? trim($validated['address']) : null,
            'is_active' => $isActive,
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
            'active'    => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = trim($validated['name']);
        if (isset($validated['code'])) $updateData['code'] = strtoupper(trim($validated['code']));
        if (array_key_exists('phone', $validated)) $updateData['phone'] = trim($validated['phone']) ?: null;
        if (array_key_exists('email', $validated)) $updateData['email'] = trim($validated['email']) ?: null;
        if (array_key_exists('address', $validated)) $updateData['address'] = trim($validated['address']) ?: null;
        if (array_key_exists('active', $validated)) $updateData['is_active'] = (bool) $validated['active'];
        elseif (array_key_exists('is_active', $validated)) $updateData['is_active'] = (bool) $validated['is_active'];

        $supplier->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil diperbarui',
            'data'    => [
                'id'        => (string) $supplier->id,
                'name'      => $supplier->name,
                'code'      => $supplier->code,
                'phone'     => $supplier->phone ?? '',
                'email'     => $supplier->email ?? '',
                'address'   => $supplier->address ?? '',
                'active'    => (bool) $supplier->is_active,
                'is_active' => (bool) $supplier->is_active,
            ],
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
