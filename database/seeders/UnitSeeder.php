<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Plano', 'abbr' => 'plano', 'type' => 'Lembar', 'is_active' => true],
            ['name' => 'Rim', 'abbr' => 'rim', 'type' => 'Lembar', 'is_active' => true],
            ['name' => 'Lembar', 'abbr' => 'lbr', 'type' => 'Lembar', 'is_active' => true],
            ['name' => 'Kilogram', 'abbr' => 'kg', 'type' => 'Berat', 'is_active' => true],
            ['name' => 'Gram', 'abbr' => 'gr', 'type' => 'Berat', 'is_active' => true],
            ['name' => 'Pcs', 'abbr' => 'pcs', 'type' => 'Unit', 'is_active' => true],
            ['name' => 'Liter', 'abbr' => 'L', 'type' => 'Volume', 'is_active' => true],
            ['name' => 'Roll', 'abbr' => 'roll', 'type' => 'Panjang', 'is_active' => true],
            ['name' => 'Pack', 'abbr' => 'pack', 'type' => 'Unit', 'is_active' => true],
            ['name' => 'Box', 'abbr' => 'box', 'type' => 'Unit', 'is_active' => true],
            ['name' => 'Meter', 'abbr' => 'm', 'type' => 'Panjang', 'is_active' => true],
            ['name' => 'Kaleng', 'abbr' => 'kln', 'type' => 'Unit', 'is_active' => false],
        ];

        $unitMap = [];
        foreach ($units as $u) {
            $model = Unit::firstOrCreate(['name' => $u['name']], $u);
            $unitMap[$u['name']] = $model->id;
        }

        // BOM Conversions
        $conversions = [
            [
                'from_unit_id' => $unitMap['Rim'] ?? null,
                'to_unit_id'   => $unitMap['Lembar'] ?? null,
                'factor'       => 500.0,
                'note'         => 'Standar percetakan: 1 Rim = 500 Lembar plano/potong',
            ],
            [
                'from_unit_id' => $unitMap['Kilogram'] ?? null,
                'to_unit_id'   => $unitMap['Gram'] ?? null,
                'factor'       => 1000.0,
                'note'         => '1 Kg tinta/lem = 1.000 Gram',
            ],
            [
                'from_unit_id' => $unitMap['Roll'] ?? null,
                'to_unit_id'   => $unitMap['Meter'] ?? null,
                'factor'       => 120.0,
                'note'         => '1 Roll Hot Stamping Foil = 120 Meter lari',
            ],
            [
                'from_unit_id' => $unitMap['Box'] ?? null,
                'to_unit_id'   => $unitMap['Pcs'] ?? null,
                'factor'       => 50.0,
                'note'         => 'Kemasan per box isi 50 pcs',
            ],
        ];

        foreach ($conversions as $conv) {
            if ($conv['from_unit_id'] && $conv['to_unit_id']) {
                UnitConversion::firstOrCreate(
                    [
                        'from_unit_id' => $conv['from_unit_id'],
                        'to_unit_id'   => $conv['to_unit_id'],
                    ],
                    $conv
                );
            }
        }
    }
}
