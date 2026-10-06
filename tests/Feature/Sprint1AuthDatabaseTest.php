<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint1AuthDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_seven_operational_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseCount('roles', 7);

        $expectedSlugs = [
            'owner',
            'manager',
            'front-office',
            'tim-design',
            'kepala-produksi',
            'quality-control',
            'staf-gudang',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug]);
        }
    }

    public function test_permission_seeder_assigns_permissions_to_owner_and_manager(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();

        $this->assertNotNull($ownerRole);
        $this->assertNotNull($managerRole);
        $this->assertGreaterThan(0, $ownerRole->permissions()->count());
        $this->assertGreaterThan(0, $managerRole->permissions()->count());
    }

    public function test_user_belongs_to_role_and_checks_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasRole('owner'));
        $this->assertFalse($user->hasRole('staf-gudang'));
        $this->assertTrue($user->hasPermission('inventory:create'));
    }

    public function test_user_has_many_refresh_tokens(): void
    {
        $user = User::factory()->create();

        $token = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'sample_refresh_token'),
            'expires_at' => now()->addDays(7),
            'revoked' => false,
        ]);

        $this->assertCount(1, $user->refreshTokens);
        $this->assertEquals($token->token_hash, $user->refreshTokens->first()->token_hash);
    }
}
