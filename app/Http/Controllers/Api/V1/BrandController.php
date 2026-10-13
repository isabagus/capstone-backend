<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     * GET /api/v1/brands
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $brands = $query->withCount(['materials'])
            ->orderBy('name', 'asc')
            ->get();

        $data = $brands->map(function ($b) {
            return [
                'id'             => (string) $b->id,
                'name'           => $b->name,
                'code'           => $b->code,
                'slug'           => $b->slug,
                'description'    => $b->description,
                'active'         => (bool) ($b->is_active ?? true),
                'material_count' => $b->materials_count ?? 0,
                'created_at'     => $b->created_at?->toIso8601String(),
                'updated_at'     => $b->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar brand berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'total' => $brands->count(),
            ],
        ]);
    }

    /**
     * Store a newly created brand.
     * POST /api/v1/brands
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:brands,name'],
            'code'        => ['required', 'string', 'max:20', 'unique:brands,code'],
            'slug'        => ['nullable', 'string', 'max:100', 'unique:brands,slug'],
            'description' => ['nullable', 'string'],
            'active'      => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $brand = Brand::create([
            'name'        => trim($validated['name']),
            'code'        => strtoupper(trim($validated['code'])),
            'slug'        => $slug,
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'is_active'   => $validated['active'] ?? ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand baru berhasil ditambahkan',
            'data'    => [
                'id'          => (string) $brand->id,
                'name'        => $brand->name,
                'code'        => $brand->code,
                'slug'        => $brand->slug,
                'description' => $brand->description,
                'active'      => (bool) $brand->is_active,
            ],
        ], 201);
    }

    /**
     * Display the specified brand.
     * GET /api/v1/brands/{id}
     */
    public function show(int $id): JsonResponse
    {
        $brand = Brand::withCount(['materials'])->find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => "Brand dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail brand berhasil diambil',
            'data'    => [
                'id'             => (string) $brand->id,
                'name'           => $brand->name,
                'code'           => $brand->code,
                'slug'           => $brand->slug,
                'description'    => $brand->description,
                'active'         => (bool) ($brand->is_active ?? true),
                'material_count' => $brand->materials_count ?? 0,
                'created_at'     => $brand->created_at?->toIso8601String(),
                'updated_at'     => $brand->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified brand.
     * PUT/PATCH /api/v1/brands/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => "Brand dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($id)],
            'code'        => ['sometimes', 'required', 'string', 'max:20', Rule::unique('brands', 'code')->ignore($id)],
            'slug'        => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('brands', 'slug')->ignore($id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'active'      => ['sometimes', 'nullable', 'boolean'],
            'is_active'   => ['sometimes', 'nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $brand->name = trim($validated['name']);
        }
        if (isset($validated['code'])) {
            $brand->code = strtoupper(trim($validated['code']));
        }
        if (isset($validated['slug'])) {
            $brand->slug = Str::slug($validated['slug']);
        } elseif (isset($validated['name']) && empty($brand->slug)) {
            $brand->slug = Str::slug($validated['name']);
        }
        if (array_key_exists('description', $validated)) {
            $brand->description = $validated['description'] ? trim($validated['description']) : null;
        }
        if (array_key_exists('active', $validated)) {
            $brand->is_active = (bool) $validated['active'];
        } elseif (array_key_exists('is_active', $validated)) {
            $brand->is_active = (bool) $validated['is_active'];
        }

        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Data brand berhasil diperbarui',
            'data'    => [
                'id'          => (string) $brand->id,
                'name'        => $brand->name,
                'code'        => $brand->code,
                'slug'        => $brand->slug,
                'description' => $brand->description,
                'active'      => (bool) $brand->is_active,
            ],
        ]);
    }

    /**
     * Remove the specified brand.
     * DELETE /api/v1/brands/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $brand = Brand::withCount(['materials'])->find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => "Brand dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        if ($brand->materials_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Brand '{$brand->name}' tidak dapat dihapus karena masih terhubung dengan {$brand->materials_count} material",
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => "Brand '{$brand->name}' berhasil dihapus",
        ]);
    }
}
