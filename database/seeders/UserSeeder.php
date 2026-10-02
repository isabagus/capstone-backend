<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TSK-S1-08: UserSeeder
 * Menyemai 7 akun pengguna dummy untuk setiap role sistem.
 * Credential standar pengembangan.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'role'     => 'owner',
                'name'     => 'Budi Santoso',
                'email'    => 'owner@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'manager',
                'name'     => 'Dewi Rahayu',
                'email'    => 'manager@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'front_office',
                'name'     => 'Ahmad Fauzi',
                'email'    => 'fo@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'tim_design',
                'name'     => 'Sari Indah',
                'email'    => 'design@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'kepala_produksi',
                'name'     => 'Rudi Hartono',
                'email'    => 'produksi@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'quality_control',
                'name'     => 'Nina Sari',
                'email'    => 'qc@packsolution.dev',
                'password' => 'password',
            ],
            [
                'role'     => 'staf_gudang',
                'name'     => 'Hendra Wijaya',
                'email'    => 'gudang@packsolution.dev',
                'password' => 'password',
            ],
        ];

        foreach ($users as $userData) {
            $roleSlug = str_replace('_', '-', $userData['role']);
            $role = Role::where('slug', $roleSlug)
                ->orWhere('slug', $userData['role'])
                ->orWhere('name', $userData['role'])
                ->first();

            if (! $role) {
                $this->command->warn("⚠️  Role '{$userData['role']}' tidak ditemukan. Jalankan RoleSeeder terlebih dahulu.");
                continue;
            }

            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'role_id'  => $role->id,
                ]
            );

            $this->command->info("✅ User [{$userData['role']}] {$userData['email']} berhasil dibuat.");
        }
    }
}
