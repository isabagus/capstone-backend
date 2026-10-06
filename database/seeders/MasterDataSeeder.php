<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed master data: 5 brands, 2 warehouses, 4 material categories, 8 units of measurement.
     */
    public function run(): void
    {
        // ── Units of Measurement ──────────────────────────────────────
        $units = [
            ['name' => 'Rim', 'code' => 'RIM', 'description' => 'Satuan kertas (1 Rim = 500 Lembar)'],
            ['name' => 'Lembar', 'code' => 'LBR', 'description' => 'Satuan potong kertas per lembar'],
            ['name' => 'Plano', 'code' => 'PLN', 'description' => 'Satuan lembaran utuh kertas cetak'],
            ['name' => 'Kilogram', 'code' => 'KG', 'description' => 'Satuan berat kilogram'],
            ['name' => 'Kaleng', 'code' => 'KLG', 'description' => 'Satuan tinta cetak per kaleng'],
            ['name' => 'Roll', 'code' => 'RLL', 'description' => 'Satuan gulungan foil/plastik'],
            ['name' => 'Pieces', 'code' => 'PCS', 'description' => 'Satuan barang jadi per unit'],
            ['name' => 'Box', 'code' => 'BOX', 'description' => 'Satuan kemasan kardus/box'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }

        // ── 5 Sub-brands ──────────────────────────────────────────────
        $brands = [
            ['name' => 'Packsolution.id', 'code' => 'PACK', 'slug' => 'packsolution'],
            ['name' => 'Estella', 'code' => 'ESTA', 'slug' => 'estella'],
            ['name' => 'Pepipapier', 'code' => 'PEPI', 'slug' => 'pepipapier'],
            ['name' => 'memoirs.print', 'code' => 'MEMO', 'slug' => 'memoirs-print'],
            ['name' => 'pikpurry', 'code' => 'PIKP', 'slug' => 'pikpurry'],
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(['code' => $brand['code']], $brand);
        }

        // ── 2 Operational Warehouses ──────────────────────────────────
        $warehouses = [
            [
                'name' => 'Gudang 1 Utama',
                'address' => 'Lokasi Gudang Utama CV Solusi Inovasi Packaging',
                'type' => 'MAIN_WAREHOUSE',
            ],
            [
                'name' => 'Gudang 2 Ruko',
                'address' => 'Lokasi Gudang Ruko CV Solusi Inovasi Packaging',
                'type' => 'STORE_WAREHOUSE',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::firstOrCreate(['name' => $warehouse['name']], $warehouse);
        }

        // ── 4 Material Categories ─────────────────────────────────────
        $categories = [
            'Bahan Baku Utama',
            'Barang Setengah Jadi',
            'Barang Jadi',
            'Spare Part',
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category]);
        }

        $this->command->info('Master data seeded: 8 units, 5 brands, 2 warehouses, 4 categories.');
    }
}
