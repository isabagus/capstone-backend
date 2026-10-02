<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * TSK-S1-04 & TSK-S1-08: RolePermissionSeeder
 * Menyemai 7 role pengguna dan matrix permissions per modul (MOD-01 hingga MOD-09).
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ================================================================
        // 1. Buat 7 Role Sistem
        // ================================================================
        $roles = [
            [
                'name'         => 'owner',
                'display_name' => 'Owner',
                'description'  => 'Akses penuh ke semua modul. Monitoring eksekutif dan audit log.',
            ],
            [
                'name'         => 'manager',
                'display_name' => 'Manager',
                'description'  => 'Approval center, prediksi kurs USD, dan manajemen laporan.',
            ],
            [
                'name'         => 'front_office',
                'display_name' => 'Front Office',
                'description'  => 'Input order pelanggan dan stock pre-validation.',
            ],
            [
                'name'         => 'tim_design',
                'display_name' => 'Tim Design',
                'description'  => 'Kalkulasi BOM, spesifikasi teknis cetak, dan penerbitan SPK.',
            ],
            [
                'name'         => 'kepala_produksi',
                'display_name' => 'Kepala Produksi',
                'description'  => 'Manajemen Kanban produksi, alokasi mesin, dan teknisi.',
            ],
            [
                'name'         => 'quality_control',
                'display_name' => 'Quality Control',
                'description'  => 'Inspeksi kelayakan produk (Pass/Fail) dan penanganan re-work.',
            ],
            [
                'name'         => 'staf_gudang',
                'display_name' => 'Staf Gudang',
                'description'  => 'Mutasi stok multi-gudang, stock opname, dan pemantauan ROP.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $roleData) {
            $roleModels[$roleData['name']] = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        // ================================================================
        // 2. Buat Permissions per Modul
        // ================================================================
        $permissions = [
            // MOD-01: Inventori Multi-Gudang
            ['name' => 'inventory.view',     'display_name' => 'Lihat Inventori',         'module' => 'MOD-01'],
            ['name' => 'inventory.create',   'display_name' => 'Tambah Material',          'module' => 'MOD-01'],
            ['name' => 'inventory.update',   'display_name' => 'Edit Material',            'module' => 'MOD-01'],
            ['name' => 'inventory.delete',   'display_name' => 'Hapus Material',           'module' => 'MOD-01'],
            ['name' => 'stock.transfer',     'display_name' => 'Transfer Stok Antar Gudang','module' => 'MOD-01'],
            ['name' => 'stock.opname',       'display_name' => 'Stock Opname',             'module' => 'MOD-01'],
            ['name' => 'batch.track',        'display_name' => 'Pelacakan Batch/Lot',      'module' => 'MOD-01'],

            // MOD-02: Front Office & Input Order
            ['name' => 'order.view',         'display_name' => 'Lihat Order',              'module' => 'MOD-02'],
            ['name' => 'order.create',       'display_name' => 'Buat Order Baru',          'module' => 'MOD-02'],
            ['name' => 'order.update',       'display_name' => 'Edit Order',               'module' => 'MOD-02'],
            ['name' => 'order.revision',     'display_name' => 'Revisi Desain Order',      'module' => 'MOD-02'],
            ['name' => 'order.approve',      'display_name' => 'Approval Order/Revisi',    'module' => 'MOD-02'],

            // MOD-03: BOM Engine
            ['name' => 'bom.view',           'display_name' => 'Lihat BOM',                'module' => 'MOD-03'],
            ['name' => 'bom.calculate',      'display_name' => 'Kalkulasi BOM',            'module' => 'MOD-03'],
            ['name' => 'bom.manage',         'display_name' => 'Kelola Formula BOM',       'module' => 'MOD-03'],

            // MOD-04: SPK Digital
            ['name' => 'spk.view',           'display_name' => 'Lihat SPK',                'module' => 'MOD-04'],
            ['name' => 'spk.generate',       'display_name' => 'Terbitkan SPK',            'module' => 'MOD-04'],
            ['name' => 'spk.download',       'display_name' => 'Download PDF SPK',         'module' => 'MOD-04'],

            // MOD-05: Produksi & QC
            ['name' => 'production.view',    'display_name' => 'Lihat Kanban Produksi',    'module' => 'MOD-05'],
            ['name' => 'production.manage',  'display_name' => 'Kelola Kanban Produksi',   'module' => 'MOD-05'],
            ['name' => 'production.assign',  'display_name' => 'Alokasi Mesin & Teknisi',  'module' => 'MOD-05'],
            ['name' => 'qc.inspect',         'display_name' => 'Inspeksi QC Pass/Fail',    'module' => 'MOD-05'],

            // MOD-06: ROP Alert
            ['name' => 'rop.view',           'display_name' => 'Lihat Alert ROP',          'module' => 'MOD-06'],
            ['name' => 'rop.manage',         'display_name' => 'Kelola Threshold ROP',     'module' => 'MOD-06'],

            // MOD-07: Kurs USD
            ['name' => 'usd.view',           'display_name' => 'Lihat Dashboard Kurs USD', 'module' => 'MOD-07'],
            ['name' => 'usd.manage',         'display_name' => 'Kelola Kurs & Prediksi',   'module' => 'MOD-07'],

            // MOD-08: Invoice & Audit Trail
            ['name' => 'invoice.view',       'display_name' => 'Lihat Invoice',            'module' => 'MOD-08'],
            ['name' => 'invoice.download',   'display_name' => 'Download PDF Invoice',     'module' => 'MOD-08'],
            ['name' => 'report.view',        'display_name' => 'Lihat Laporan',            'module' => 'MOD-08'],
            ['name' => 'report.export',      'display_name' => 'Export Laporan Excel/PDF', 'module' => 'MOD-08'],
            ['name' => 'audit.view',         'display_name' => 'Lihat Audit Log',          'module' => 'MOD-08'],

            // MOD-09: User Management
            ['name' => 'user.view',          'display_name' => 'Lihat Data Pengguna',      'module' => 'MOD-09'],
            ['name' => 'user.manage',        'display_name' => 'Kelola Pengguna & Role',   'module' => 'MOD-09'],
        ];

        $permModels = [];
        foreach ($permissions as $permData) {
            $permData['description'] = $permData['description'] ?? null;
            $permModels[$permData['name']] = Permission::firstOrCreate(
                ['name' => $permData['name']],
                $permData
            );
        }

        // ================================================================
        // 3. TSK-S1-04: Assign Permissions ke Setiap Role (Permission Matrix)
        // ================================================================

        // OWNER - Akses penuh semua modul
        $roleModels['owner']->permissions()->sync(
            collect($permModels)->pluck('id')->toArray()
        );

        // MANAGER - Approval, laporan, kurs USD, inventori view, produksi view
        $roleModels['manager']->permissions()->sync(
            collect($permModels)->only([
                'inventory.view', 'stock.transfer', 'stock.opname',
                'order.view', 'order.approve', 'order.update',
                'bom.view', 'spk.view', 'spk.download',
                'production.view', 'qc.inspect',
                'rop.view', 'rop.manage',
                'usd.view', 'usd.manage',
                'invoice.view', 'invoice.download',
                'report.view', 'report.export',
                'audit.view',
                'user.view',
            ])->pluck('id')->toArray()
        );

        // FRONT OFFICE - Input order dan lihat stok
        $roleModels['front_office']->permissions()->sync(
            collect($permModels)->only([
                'inventory.view',
                'order.view', 'order.create', 'order.update', 'order.revision',
                'rop.view',
            ])->pluck('id')->toArray()
        );

        // TIM DESIGN - BOM, SPK, dan lihat order
        $roleModels['tim_design']->permissions()->sync(
            collect($permModels)->only([
                'order.view',
                'bom.view', 'bom.calculate', 'bom.manage',
                'spk.view', 'spk.generate', 'spk.download',
                'inventory.view',
            ])->pluck('id')->toArray()
        );

        // KEPALA PRODUKSI - Kanban, alokasi mesin, lihat SPK
        $roleModels['kepala_produksi']->permissions()->sync(
            collect($permModels)->only([
                'order.view',
                'spk.view', 'spk.download',
                'production.view', 'production.manage', 'production.assign',
                'qc.inspect',
            ])->pluck('id')->toArray()
        );

        // QUALITY CONTROL - Inspeksi QC dan lihat produksi
        $roleModels['quality_control']->permissions()->sync(
            collect($permModels)->only([
                'order.view',
                'production.view',
                'qc.inspect',
                'spk.view',
            ])->pluck('id')->toArray()
        );

        // STAF GUDANG - Mutasi stok, opname, alert ROP
        $roleModels['staf_gudang']->permissions()->sync(
            collect($permModels)->only([
                'inventory.view', 'inventory.create', 'inventory.update',
                'stock.transfer', 'stock.opname', 'batch.track',
                'rop.view',
            ])->pluck('id')->toArray()
        );

        $this->command->info('✅ TSK-S1-04: 7 Role & Permission Matrix berhasil disemai.');
    }
}
