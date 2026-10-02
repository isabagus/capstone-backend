<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Manager', 'slug' => 'manager'],
            ['name' => 'Front Office', 'slug' => 'front-office'],
            ['name' => 'Tim Design', 'slug' => 'tim-design'],
            ['name' => 'Kepala Produksi', 'slug' => 'kepala-produksi'],
            ['name' => 'Quality Control', 'slug' => 'quality-control'],
            ['name' => 'Staf Gudang', 'slug' => 'staf-gudang'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
