<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * GET /api/master/vendors
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('specialty', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $vendors = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $vendors,
            'total'   => $vendors->count(),
        ]);
    }

    /**
     * POST /api/master/vendors
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:vendors,code',
            'name'           => 'required|string|max:120',
            'specialty'      => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:100',
            'address'        => 'nullable|string',
            'is_active'      => 'nullable|boolean',
        ]);

        $vendor = Vendor::create([
            'code'           => strtoupper($validated['code']),
            'name'           => $validated['name'],
            'specialty'      => $validated['specialty'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor mitra berhasil ditambahkan.',
            'data'    => $vendor,
        ], 201);
    }

    /**
     * GET /api/master/vendors/{vendor}
     */
    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $vendor,
        ]);
    }

    /**
     * PUT/PATCH /api/master/vendors/{vendor}
     */
    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', Rule::unique('vendors')->ignore($vendor->id)],
            'name'           => 'required|string|max:120',
            'specialty'      => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:100',
            'address'        => 'nullable|string',
            'is_active'      => 'nullable|boolean',
        ]);

        $vendor->update([
            'code'           => strtoupper($validated['code']),
            'name'           => $validated['name'],
            'specialty'      => $validated['specialty'] ?? $vendor->specialty,
            'contact_person' => $validated['contact_person'] ?? $vendor->contact_person,
            'phone'          => $validated['phone'] ?? $vendor->phone,
            'email'          => $validated['email'] ?? $vendor->email,
            'address'        => $validated['address'] ?? $vendor->address,
            'is_active'      => $validated['is_active'] ?? $vendor->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data vendor mitra berhasil diperbarui.',
            'data'    => $vendor,
        ]);
    }

    /**
     * DELETE /api/master/vendors/{vendor}
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor mitra berhasil dihapus.',
        ]);
    }
}
