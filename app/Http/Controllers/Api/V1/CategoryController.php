<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     * GET /api/v1/categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $query->withCount(['materials'])
            ->orderBy('name', 'asc')
            ->get();

        $data = $categories->map(function ($c) {
            $count = $c->materials_count ?? 0;
            return [
                'id'              => (string) $c->id,
                'name'            => $c->name,
                'kind'            => $c->kind ?? 'material',
                'active'          => (bool) ($c->is_active ?? true),
                'materials_count' => $count,
                'itemCount'       => $count,
                'created_at'      => $c->created_at?->toIso8601String(),
                'updated_at'      => $c->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'total' => $categories->count(),
            ],
        ]);
    }

    /**
     * Store a newly created category.
     * POST /api/v1/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'kind'      => ['nullable', 'string', 'max:30'],
            'active'    => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kind = $validated['kind'] ?? 'material';
        $exists = Category::where('name', trim($validated['name']))
            ->where('kind', $kind)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Kategori '{$validated['name']}' untuk jenis {$kind} sudah ada.",
            ], 422);
        }

        $category = Category::create([
            'name'      => trim($validated['name']),
            'kind'      => $kind,
            'is_active' => $validated['active'] ?? ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori baru berhasil ditambahkan',
            'data'    => [
                'id'        => (string) $category->id,
                'name'      => $category->name,
                'kind'      => $category->kind,
                'active'    => (bool) $category->is_active,
                'itemCount' => 0,
            ],
        ], 201);
    }

    /**
     * Display the specified category.
     * GET /api/v1/categories/{id}
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::withCount(['materials'])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => "Kategori dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil diambil',
            'data'    => [
                'id'              => (string) $category->id,
                'name'            => $category->name,
                'kind'            => $category->kind ?? 'material',
                'active'          => (bool) ($category->is_active ?? true),
                'materials_count' => $category->materials_count ?? 0,
                'itemCount'       => $category->materials_count ?? 0,
            ],
        ]);
    }

    /**
     * Update the specified category.
     * PUT/PATCH /api/v1/categories/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => "Kategori dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:100'],
            'kind'      => ['nullable', 'string', 'max:30'],
            'active'    => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $kind = $validated['kind'] ?? $category->kind;
            $exists = Category::where('name', trim($validated['name']))
                ->where('kind', $kind)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Kategori '{$validated['name']}' sudah ada.",
                ], 422);
            }
            $category->name = trim($validated['name']);
        }

        if (isset($validated['kind'])) {
            $category->kind = $validated['kind'];
        }

        if (array_key_exists('active', $validated)) {
            $category->is_active = (bool) $validated['active'];
        } elseif (array_key_exists('is_active', $validated)) {
            $category->is_active = (bool) $validated['is_active'];
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Data kategori berhasil diperbarui',
            'data'    => [
                'id'        => (string) $category->id,
                'name'      => $category->name,
                'kind'      => $category->kind,
                'active'    => (bool) $category->is_active,
                'itemCount' => $category->materials()->count(),
            ],
        ]);
    }

    /**
     * Remove the specified category.
     * DELETE /api/v1/categories/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::withCount(['materials'])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => "Kategori dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        if ($category->materials_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kategori '{$category->name}' tidak dapat dihapus karena masih digunakan oleh {$category->materials_count} material",
                'error_code' => 'CATEGORY_NOT_EMPTY',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "Kategori '{$category->name}' berhasil dihapus",
        ]);
    }
}
