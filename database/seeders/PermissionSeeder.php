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
    }
}
