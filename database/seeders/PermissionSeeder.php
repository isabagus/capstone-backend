<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Inventory', 'slug' => 'inventory:read'],
            ['name' => 'Create Inventory Material', 'slug' => 'inventory:create'],
            ['name' => 'Transfer Stock', 'slug' => 'inventory:transfer'],
            ['name' => 'Stock Opname', 'slug' => 'inventory:opname'],
            ['name' => 'Create Order', 'slug' => 'order:create'],
            ['name' => 'Approve Special Order', 'slug' => 'order:approve'],
            ['name' => 'Calculate BOM', 'slug' => 'bom:calculate'],
            ['name' => 'Issue SPK', 'slug' => 'spk:issue'],
            ['name' => 'Update Production Kanban', 'slug' => 'production:update'],
            ['name' => 'Inspect Quality Control', 'slug' => 'qc:inspect'],
            ['name' => 'View USD Analytics', 'slug' => 'analytics:view'],
            ['name' => 'View Audit Log', 'slug' => 'audit:view'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Attach all permissions to Owner and Manager
        $allPermissions = Permission::all();
        $owner = Role::where('slug', 'owner')->first();
        $manager = Role::where('slug', 'manager')->first();

        if ($owner) {
            $owner->permissions()->sync($allPermissions->pluck('id'));
        }
        if ($manager) {
            $manager->permissions()->sync($allPermissions->pluck('id'));
        }

        // Staf Gudang — inventory operations
        $staffGudang = Role::where('slug', 'staf-gudang')->first();
        if ($staffGudang) {
            $staffGudang->permissions()->sync(
                Permission::whereIn('slug', ['inventory:read', 'inventory:create', 'inventory:transfer', 'inventory:opname'])
                    ->pluck('id')
            );
        }

        // Front Office — order operations
        $frontOffice = Role::where('slug', 'front-office')->first();
        if ($frontOffice) {
            $frontOffice->permissions()->sync(
                Permission::whereIn('slug', ['inventory:read', 'order:create'])
                    ->pluck('id')
            );
        }

        // Tim Design — BOM & order view
        $timDesign = Role::where('slug', 'tim-design')->first();
        if ($timDesign) {
            $timDesign->permissions()->sync(
                Permission::whereIn('slug', ['inventory:read', 'order:create', 'bom:calculate'])
                    ->pluck('id')
            );
        }

        // Kepala Produksi — SPK & production
        $kepalaProduksi = Role::where('slug', 'kepala-produksi')->first();
        if ($kepalaProduksi) {
            $kepalaProduksi->permissions()->sync(
                Permission::whereIn('slug', ['inventory:read', 'spk:issue', 'production:update'])
                    ->pluck('id')
            );
        }

        // Quality Control — QC inspect
        $qc = Role::where('slug', 'quality-control')->first();
        if ($qc) {
            $qc->permissions()->sync(
                Permission::whereIn('slug', ['inventory:read', 'qc:inspect'])
                    ->pluck('id')
            );
        }
    }
}
