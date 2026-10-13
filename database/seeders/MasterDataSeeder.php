<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds for Master Data:
     * - Warehouses (2 gudang)
     * - Brands (5 brand)
     * - Categories (4 kategori)
     * - Suppliers (4 supplier)
     * - Materials & Brands Pivot
     * - Initial Warehouse Stocks
     */
    public function run(): void
    {
        // 1. Warehouses
        $warehouses = [
            [
                'name' => 'Gudang 1 (Utama / Pabrik)',
                'address' => 'Kawasan Industri Packaging No. 12, Jawa Timur',
                'type' => 'MAIN_WAREHOUSE',
            ],
            [
                'name' => 'Gudang 2 (Toko / Ruko)',
                'address' => 'Ruko Sentra Niaga Blok B-5, Jawa Timur',
                'type' => 'STORE_WAREHOUSE',
            ],
        ];

        $warehouseModels = [];
        foreach ($warehouses as $wh) {
            $warehouseModels[] = Warehouse::firstOrCreate(['name' => $wh['name']], $wh);
        }

        // 2. Brands (5 Brand)
        $brands = [
            ['name' => 'Packsolution.id', 'code' => 'PACK', 'slug' => 'packsolution-id'],
            ['name' => 'Estella Digital Printing', 'code' => 'EST', 'slug' => 'estella'],
            ['name' => 'Pepipapier', 'code' => 'PEPI', 'slug' => 'pepipapier'],
            ['name' => 'memoirs.print', 'code' => 'MEM', 'slug' => 'memoirs-print'],
            ['name' => 'pikpurry', 'code' => 'PIK', 'slug' => 'pikpurry'],
        ];

        $brandModels = [];
        foreach ($brands as $brand) {
            $brandModels[] = Brand::firstOrCreate(['code' => $brand['code']], $brand);
        }

        // 3. Categories (4 Kategori)
        $categories = [
            ['name' => 'Kertas Plano & Karton'],
            ['name' => 'Tinta & Kimia Cetak'],
            ['name' => 'Finishing & Foil'],
            ['name' => 'Lem & Consumables'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[$cat['name']] = Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // 4. Suppliers
        $suppliers = [
            [
                'name' => 'PT Surya Kencana Paper',
                'code' => 'SUP-SKP',
                'phone' => '021-5551234',
                'email' => 'sales@suryakencana.co.id',
                'address' => 'Jl. Daan Mogot KM 14, Jakarta Barat',
                'is_active' => true,
            ],
            [
                'name' => 'PT Kurz Indonesia Foil',
                'code' => 'SUP-KURZ',
                'phone' => '021-5555678',
                'email' => 'order@kurz.co.id',
                'address' => 'Kawasan Industri Jababeka, Cikarang',
                'is_active' => true,
            ],
            [
                'name' => 'CV Toyo Ink Supply',
                'code' => 'SUP-TOYO',
                'phone' => '031-7778899',
                'email' => 'supply@toyoink.co.id',
                'address' => 'Jl. Rungkut Industri No. 45, Surabaya',
                'is_active' => true,
            ],
            [
                'name' => 'PT Henkel Adhesive Prima',
                'code' => 'SUP-HNKL',
                'phone' => '021-8889900',
                'email' => 'support@henkel.co.id',
                'address' => 'Kawasan MM2100, Cikarang Barat',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $sup) {
            Supplier::firstOrCreate(['code' => $sup['code']], $sup);
        }

        // 5. Materials (Katalog Bahan Sesuai PRD)
        $materials = [
            [
                'sku' => 'MAT-AC260-65100',
                'name' => 'Art Carton 260 GSM (65x100 cm)',
                'category_id' => $categoryModels['Kertas Plano & Karton']->id,
                'unit' => 'Plano',
                'safety_stock' => 50,
                'reorder_point' => 100,
                'stock_g1' => 450,
                'stock_g2' => 80,
            ],
            [
                'sku' => 'MAT-AC310-79109',
                'name' => 'Art Carton 310 GSM (79x109 cm)',
                'category_id' => $categoryModels['Kertas Plano & Karton']->id,
                'unit' => 'Plano',
                'safety_stock' => 40,
                'reorder_point' => 80,
                'stock_g1' => 320,
                'stock_g2' => 45,
            ],
            [
                'sku' => 'MAT-IV300-79109',
                'name' => 'Ivory Board 300 GSM (79x109 cm)',
                'category_id' => $categoryModels['Kertas Plano & Karton']->id,
                'unit' => 'Plano',
                'safety_stock' => 30,
                'reorder_point' => 60,
                'stock_g1' => 180,
                'stock_g2' => 30,
            ],
            [
                'sku' => 'MAT-KL275-65100',
                'name' => 'Kraft Liner 275 GSM (65x100 cm)',
                'category_id' => $categoryModels['Kertas Plano & Karton']->id,
                'unit' => 'Plano',
                'safety_stock' => 50,
                'reorder_point' => 100,
                'stock_g1' => 520,
                'stock_g2' => 110,
            ],
            [
                'sku' => 'MAT-DP350-79109',
                'name' => 'Duplex Board 350 GSM (79x109 cm)',
                'category_id' => $categoryModels['Kertas Plano & Karton']->id,
                'unit' => 'Plano',
                'safety_stock' => 50,
                'reorder_point' => 100,
                'stock_g1' => 290,
                'stock_g2' => 50,
            ],
            [
                'sku' => 'MAT-INK-CYAN',
                'name' => 'Tinta Offset Toyo Process Cyan',
                'category_id' => $categoryModels['Tinta & Kimia Cetak']->id,
                'unit' => 'Kg',
                'safety_stock' => 10,
                'reorder_point' => 20,
                'stock_g1' => 65,
                'stock_g2' => 12,
            ],
            [
                'sku' => 'MAT-INK-MAGENTA',
                'name' => 'Tinta Offset Toyo Process Magenta',
                'category_id' => $categoryModels['Tinta & Kimia Cetak']->id,
                'unit' => 'Kg',
                'safety_stock' => 10,
                'reorder_point' => 20,
                'stock_g1' => 58,
                'stock_g2' => 10,
            ],
            [
                'sku' => 'MAT-INK-YELLOW',
                'name' => 'Tinta Offset Toyo Process Yellow',
                'category_id' => $categoryModels['Tinta & Kimia Cetak']->id,
                'unit' => 'Kg',
                'safety_stock' => 10,
                'reorder_point' => 20,
                'stock_g1' => 62,
                'stock_g2' => 14,
            ],
            [
                'sku' => 'MAT-INK-BLACK',
                'name' => 'Tinta Offset Toyo Process Black',
                'category_id' => $categoryModels['Tinta & Kimia Cetak']->id,
                'unit' => 'Kg',
                'safety_stock' => 15,
                'reorder_point' => 30,
                'stock_g1' => 85,
                'stock_g2' => 18,
            ],
            [
                'sku' => 'MAT-FL-GOLD120',
                'name' => 'Kurz Foil Hot Stamping Gold 120m',
                'category_id' => $categoryModels['Finishing & Foil']->id,
                'unit' => 'Roll',
                'safety_stock' => 10,
                'reorder_point' => 25,
                'stock_g1' => 42,
                'stock_g2' => 8,
            ],
            [
                'sku' => 'MAT-GL-TECHNO',
                'name' => 'Lem Hotmelt Henkel Technomelt Packaging',
                'category_id' => $categoryModels['Lem & Consumables']->id,
                'unit' => 'Kg',
                'safety_stock' => 20,
                'reorder_point' => 40,
                'stock_g1' => 120,
                'stock_g2' => 25,
            ],
        ];

        $allBrandIds = collect($brandModels)->pluck('id')->toArray();

        foreach ($materials as $matData) {
            $stockG1 = $matData['stock_g1'];
            $stockG2 = $matData['stock_g2'];
            unset($matData['stock_g1'], $matData['stock_g2']);

            $material = Material::firstOrCreate(['sku' => $matData['sku']], $matData);

            // Hubungkan ke semua 5 brand (atau subset)
            $material->brands()->sync($allBrandIds);

            // Set stok di Gudang 1
            WarehouseStock::updateOrCreate(
                [
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouseModels[0]->id,
                ],
                [
                    'qty_available' => $stockG1,
                    'qty_reserved' => 0.00,
                ]
            );

            // Set stok di Gudang 2
            WarehouseStock::updateOrCreate(
                [
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouseModels[1]->id,
                ],
                [
                    'qty_available' => $stockG2,
                    'qty_reserved' => 0.00,
                ]
            );
        }
    }
}
