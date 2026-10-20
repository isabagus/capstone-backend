<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Resolusi ID Brand yang fleksibel dari nama string ("Packsolution.id", "Estella", dsb) atau ID.
     */
    protected function resolveBrandId(mixed $value): int
    {
        if (is_numeric($value)) {
            $b = Brand::find((int) $value);
            if ($b) return $b->id;
        }

        $val = strtolower(trim((string) $value));
        if (str_contains($val, 'pack')) return 1;
        if (str_contains($val, 'estella')) return 2;
        if (str_contains($val, 'pepi')) return 3;
        if (str_contains($val, 'memoirs')) return 4;
        if (str_contains($val, 'pix') || str_contains($val, 'pik')) return 5;

        $b = Brand::where('name', 'like', "%{$value}%")->orWhere('code', $value)->first();
        return $b ? $b->id : 1;
    }

    /**
     * Menampilkan daftar produk jadi & setengah jadi transit.
     * GET /api/v1/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with([
            'brand:id,name,code,slug',
            'category:id,name',
            'stocks.warehouse:id,name,type',
        ]);

        // Filter search SKU atau Nama Produk
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter Brand
        if ($request->filled('brand_id') && $request->input('brand_id') !== 'all') {
            $brandId = $this->resolveBrandId($request->input('brand_id'));
            $query->where('brand_id', $brandId);
        }

        // Filter Kategori
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter Tipe (Barang Jadi vs Setengah Jadi)
        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        $products = $query->orderBy('name', 'asc')->get();

        $data = $products->map(function ($p) {
            $stocks = $p->stocks;
            $totalAvailable = (float) $stocks->sum('qty_available');
            $totalReserved = (float) $stocks->sum('qty_reserved');

            $warehouseBreakdown = $stocks->map(function ($ws) {
                return [
                    'warehouse_id'   => $ws->warehouse_id,
                    'warehouse_name' => $ws->warehouse?->name,
                    'warehouse_type' => $ws->warehouse?->type,
                    'qty_available'  => (float) $ws->qty_available,
                    'qty_reserved'   => (float) $ws->qty_reserved,
                ];
            });

            $isBelowMin = $totalAvailable <= (float) $p->min_stock;

            return [
                'id'               => $p->id,
                'sku'              => $p->sku,
                'name'             => $p->name,
                'brand_id'         => $p->brand_id,
                'brand'            => $p->brand?->name ?? 'Packsolution.id',
                'brand_code'       => $p->brand?->code,
                'type'             => $p->type,
                'category_id'      => $p->category_id,
                'category_name'    => $p->category?->name ?? 'Produk Kemasan',
                'unit'             => $p->unit,
                'min_stock'        => (float) $p->min_stock,
                'total_available'  => $totalAvailable,
                'total_reserved'   => $totalReserved,
                'is_low_stock'     => $isBelowMin,
                'warehouse_stocks' => $warehouseBreakdown,
                'created_at'       => $p->created_at?->toIso8601String(),
                'updated_at'       => $p->updated_at?->toIso8601String(),
            ];
        });

        if ($request->boolean('low_stock_only') || $request->input('status') === 'low') {
            $data = $data->filter(fn ($item) => $item['is_low_stock'])->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk jadi & setengah jadi berhasil dimuat',
            'data'    => $data,
            'meta'    => [
                'total'     => $data->count(),
                'low_stock' => $data->where('is_low_stock', true)->count(),
            ],
        ]);
    }

    /**
     * Menyimpan master produk kemasan baru.
     * POST /api/v1/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku'            => ['required', 'string', 'max:50', 'unique:products,sku'],
            'name'           => ['required', 'string', 'max:150'],
            'brand_id'       => ['nullable'],
            'type'           => ['nullable', 'string', 'in:Barang Jadi,Setengah Jadi'],
            'category_id'    => ['nullable', 'integer', 'exists:categories,id'],
            'unit'           => ['required', 'string', 'max:20'],
            'min_stock'      => ['nullable', 'numeric', 'min:0'],
            'initial_stocks' => ['nullable', 'array'],
            'initial_stocks.*.warehouse_id'  => ['required_with:initial_stocks', 'integer', 'exists:warehouses,id'],
            'initial_stocks.*.qty_available' => ['required_with:initial_stocks', 'numeric', 'min:0'],
        ]);

        $brandId = $this->resolveBrandId($request->input('brand_id', 1));

        return DB::transaction(function () use ($validated, $brandId) {
            $product = Product::create([
                'sku'         => strtoupper(trim($validated['sku'])),
                'name'        => trim($validated['name']),
                'brand_id'    => $brandId,
                'type'        => $validated['type'] ?? 'Barang Jadi',
                'category_id' => $validated['category_id'] ?? null,
                'unit'        => $validated['unit'],
                'min_stock'   => $validated['min_stock'] ?? 0.00,
            ]);

            // Inisialisasi stok fisik per gudang
            $warehouses = Warehouse::all();
            $initialStockMap = collect($validated['initial_stocks'] ?? [])->keyBy('warehouse_id');

            foreach ($warehouses as $wh) {
                $qty = 0.00;
                if ($initialStockMap->has($wh->id)) {
                    $qty = (float) $initialStockMap->get($wh->id)['qty_available'];
                }

                ProductStock::create([
                    'product_id'    => $product->id,
                    'warehouse_id'  => $wh->id,
                    'qty_available' => $qty,
                    'qty_reserved'  => 0.00,
                ]);
            }

            $product->load(['brand', 'category', 'stocks.warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Produk baru berhasil ditambahkan',
                'data'    => $product,
            ], 201);
        });
    }

    /**
     * Menampilkan detail satu produk.
     * GET /api/v1/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with([
            'brand',
            'category',
            'stocks.warehouse',
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Produk dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil diambil',
            'data'    => $product,
        ]);
    }

    /**
     * Memperbarui data produk kemasan.
     * PUT/PATCH /api/v1/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Produk dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        $validated = $request->validate([
            'sku'         => ['sometimes', 'required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product->id)],
            'name'        => ['sometimes', 'required', 'string', 'max:150'],
            'brand_id'    => ['sometimes', 'nullable'],
            'type'        => ['sometimes', 'nullable', 'string', 'in:Barang Jadi,Setengah Jadi'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'unit'        => ['sometimes', 'required', 'string', 'max:20'],
            'min_stock'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $brandId = array_key_exists('brand_id', $validated)
            ? $this->resolveBrandId($validated['brand_id'])
            : $product->brand_id;

        $product->update([
            'sku'         => isset($validated['sku']) ? strtoupper(trim($validated['sku'])) : $product->sku,
            'name'        => isset($validated['name']) ? trim($validated['name']) : $product->name,
            'brand_id'    => $brandId,
            'type'        => $validated['type'] ?? $product->type,
            'category_id' => array_key_exists('category_id', $validated) ? $validated['category_id'] : $product->category_id,
            'unit'        => $validated['unit'] ?? $product->unit,
            'min_stock'   => array_key_exists('min_stock', $validated) ? ($validated['min_stock'] ?? 0.00) : $product->min_stock,
        ]);

        $product->load(['brand', 'category', 'stocks.warehouse']);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data'    => $product,
        ]);
    }

    /**
     * Menghapus produk dengan proteksi integritas stok.
     * DELETE /api/v1/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Produk dengan ID {$id} tidak ditemukan",
            ], 404);
        }

        // Cek apakah ada stok aktif > 0
        $totalStock = $product->stocks()->sum('qty_available');
        if ($totalStock > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat menghapus produk yang masih memiliki stok fisik ({$totalStock} {$product->unit}). Lakukan penyesuaian stok terlebih dahulu.",
                'error_code' => 'ACTIVE_STOCK_EXISTS',
            ], 400);
        }

        return DB::transaction(function () use ($product) {
            $product->stocks()->delete();
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus',
            ]);
        });
    }
}
