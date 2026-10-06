<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Sprint2MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;

    protected User $stafGudangUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $stafRole = Role::where('slug', 'staf-gudang')->first();

        $this->ownerUser = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->stafGudangUser = User::factory()->create(['role_id' => $stafRole->id]);
    }

    public function test_categories_index_and_store(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $storeResponse = $this->postJson('/api/v1/categories', [
            'name' => 'Kertas Art Paper',
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('name', 'Kertas Art Paper');

        $indexResponse = $this->getJson('/api/v1/categories');
        $indexResponse->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_brands_store_allowed_for_owner_forbidden_for_staf_gudang(): void
    {
        // Staf gudang try store -> forbidden (403)
        Sanctum::actingAs($this->stafGudangUser);
        $this->postJson('/api/v1/brands', [
            'name' => 'Estella Packaging',
            'code' => 'EST',
            'slug' => 'estella-packaging',
        ])->assertStatus(403);

        // Owner try store -> success (201)
        Sanctum::actingAs($this->ownerUser);
        $this->postJson('/api/v1/brands', [
            'name' => 'Estella Packaging',
            'code' => 'EST',
            'slug' => 'estella-packaging',
        ])->assertStatus(201)
            ->assertJsonPath('slug', 'estella-packaging');
    }

    public function test_warehouses_index_and_creation(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $this->postJson('/api/v1/warehouses', [
            'name' => 'Gudang Utama Ruko',
            'address' => 'Jl. Packaging No 1',
            'type' => 'MAIN_WAREHOUSE',
        ])->assertStatus(201)
            ->assertJsonPath('type', 'MAIN_WAREHOUSE');

        $this->getJson('/api/v1/warehouses')
            ->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_suppliers_crud_operations(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Create
        $createRes = $this->postJson('/api/v1/suppliers', [
            'name' => 'PT Paperindo Jaya',
            'code' => 'SUP-001',
            'phone' => '081234567890',
            'email' => 'budi@paperindo.com',
            'address' => 'Surabaya',
        ]);

        $createRes->assertStatus(201);
        $supplierId = $createRes->json('id');

        // Read
        $this->getJson('/api/v1/suppliers/' . $supplierId)
            ->assertStatus(200)
            ->assertJsonPath('name', 'PT Paperindo Jaya');

        // Update
        $this->putJson('/api/v1/suppliers/' . $supplierId, [
            'name' => 'PT Paperindo Jaya Utama',
        ])->assertStatus(200)
            ->assertJsonPath('name', 'PT Paperindo Jaya Utama');

        // Soft Delete (Deactivate)
        $this->deleteJson('/api/v1/suppliers/' . $supplierId)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Supplier deactivated successfully.');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplierId,
            'is_active' => false,
        ]);
    }
}
