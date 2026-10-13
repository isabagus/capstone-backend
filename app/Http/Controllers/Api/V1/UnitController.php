<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    /**
     * Display a listing of units.
     * GET /api/v1/units
     */
    public function index(Request $request): JsonResponse
    {
        $query = Unit::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('abbr', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $units = $query->orderBy('name', 'asc')->get();

        $data = $units->map(function ($u) {
            return [
                'id'         => (string) $u->id,
                'name'       => $u->name,
                'abbr'       => $u->abbr,
                'type'       => $u->type,
                'active'     => (bool) $u->is_active,
                'created_at' => $u->created_at?->toIso8601String(),
                'updated_at' => $u->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar satuan berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'total' => $units->count(),
            ],
        ]);
    }

    /**
     * Store a newly created unit.
     * POST /api/v1/units
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:50', 'unique:units,name'],
            'abbr'      => ['required', 'string', 'max:20'],
            'type'      => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit = Unit::create([
            'name'      => trim($validated['name']),
            'abbr'      => trim($validated['abbr']),
            'type'      => trim($validated['type']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Satuan baru berhasil ditambahkan',
            'data'    => [
                'id'     => (string) $unit->id,
                'name'   => $unit->name,
                'abbr'   => $unit->abbr,
                'type'   => $unit->type,
                'active' => (bool) $unit->is_active,
            ],
        ], 201);
    }

    /**
     * Display the specified unit.
     * GET /api/v1/units/{id}
     */
    public function show(int $id): JsonResponse
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => "Satuan dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail satuan berhasil diambil',
            'data'    => [
                'id'     => (string) $unit->id,
                'name'   => $unit->name,
                'abbr'   => $unit->abbr,
                'type'   => $unit->type,
                'active' => (bool) $unit->is_active,
            ],
        ]);
    }

    /**
     * Update the specified unit.
     * PUT/PATCH /api/v1/units/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => "Satuan dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($id)],
            'abbr'      => ['sometimes', 'required', 'string', 'max:20'],
            'type'      => ['sometimes', 'required', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'active'    => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $unit->name = trim($validated['name']);
        }
        if (isset($validated['abbr'])) {
            $unit->abbr = trim($validated['abbr']);
        }
        if (isset($validated['type'])) {
            $unit->type = trim($validated['type']);
        }
        if (isset($validated['is_active'])) {
            $unit->is_active = $validated['is_active'];
        } elseif (isset($validated['active'])) {
            $unit->is_active = $validated['active'];
        }

        $unit->save();

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil diperbarui',
            'data'    => [
                'id'     => (string) $unit->id,
                'name'   => $unit->name,
                'abbr'   => $unit->abbr,
                'type'   => $unit->type,
                'active' => (bool) $unit->is_active,
            ],
        ]);
    }

    /**
     * Remove the specified unit.
     * DELETE /api/v1/units/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $unit = Unit::withCount(['conversionsFrom', 'conversionsTo'])->find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => "Satuan dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $totalConversions = ($unit->conversions_from_count ?? 0) + ($unit->conversions_to_count ?? 0);
        if ($totalConversions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Satuan '{$unit->name}' tidak dapat dihapus karena digunakan dalam {$totalConversions} aturan konversi",
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => "Satuan '{$unit->name}' berhasil dihapus",
        ]);
    }
}
