<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Material;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Sprint2InventoryTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;

    protected User $stafGudangUser;

    protected Warehouse $mainWarehouse;

    protected Warehouse $branchWarehouse;

    protected Category $category;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $stafRole = Role::where('slug', 'staf-gudang')->first();

        $this->ownerUser = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->stafGudangUser = User::factory()->create(['role_id' => $stafRole->id]);

        $this->mainWarehouse = Warehouse::create([
            'name' => 'Gudang Utama',
            'type' => 'MAIN_WAREHOUSE',
            'address' => 'Jl. Industri No. 1',
        ]);

        $this->branchWarehouse = Warehouse::create([
            'name' => 'Gudang Ruko',
            'type' => 'STORE_WAREHOUSE',
            'address' => 'Jl. Ruko No. 5',
        ]);

        $this->category = Category::create(['name' => 'Bahan Baku Kertas']);

        $this->supplier = Supplier::create([
            'name' => 'PT Suplier Kertas',
            'code' => 'SUP-PAPER-01',
        ]);

        $this->material = Material::create([
            'sku' => 'MAT-ART-260',
            'name' => 'Kertas Art Paper 260gr',
            'category_id' => $this->category->id,
            'unit' => 'rim',
            'safety_stock' => 10,
            'reorder_point' => 25,
        ]);
    }

    public function test_goods_receipt_increases_stock_and_creates_mutation_and_batch_lot(): void
    {
        Sanctum::actingAs($this->stafGudangUser);

        $response = $this->postJson('/api/v1/goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'supplier_name' => 'PT Suplier Kertas',
            'warehouse_id' => $this->mainWarehouse->id,
            'received_date' => '2026-09-01',
            'po_number' => 'PO-2026-001',
            'items' => [
                [
                    'material_id' => $this->material->id,
                    'qty_received' => 50,
                    'batch_number' => 'LOT-2026-001',
                    'expiry_date' => '2027-12-31',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('warehouse_id', $this->mainWarehouse->id);

        // Verify WarehouseStock increased
        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 50,
        ]);

        // Verify BatchLot created
        $this->assertDatabaseHas('batch_lots', [
            'material_id' => $this->material->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'batch_number' => 'LOT-2026-001',
            'qty' => 50,
        ]);

        // Verify StockMutation recorded
        $this->assertDatabaseHas('stock_mutations', [
            'material_id' => $this->material->id,
            'target_warehouse_id' => $this->mainWarehouse->id,
            'type' => 'IN',
            'qty' => 50,
        ]);
    }

    public function test_stock_transfer_moves_stock_between_warehouses(): void
    {
        // Seed initial stock in main warehouse
        WarehouseStock::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);

        Sanctum::actingAs($this->stafGudangUser);

        $response = $this->postJson('/api/v1/stock-transfers', [
            'origin_warehouse_id' => $this->mainWarehouse->id,
            'target_warehouse_id' => $this->branchWarehouse->id,
            'notes' => 'Transfer ke Gudang Ruko',
            'items' => [
                [
                    'material_id' => $this->material->id,
                    'qty' => 30,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // Check main warehouse reduced (100 - 30 = 70)
        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 70,
        ]);

        // Check branch warehouse increased (30)
        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $this->branchWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 30,
        ]);
    }

    public function test_stock_opname_flow_creation_and_approval(): void
    {
        // Initial stock 50
        WarehouseStock::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 50,
            'qty_reserved' => 0,
        ]);

        // 1. Staf gudang creates stock opname (counted 45 instead of 50 -> discrepancy -5)
        Sanctum::actingAs($this->stafGudangUser);

        $createRes = $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $this->mainWarehouse->id,
            'details' => [
                [
                    'material_id' => $this->material->id,
                    'physical_qty' => 45,
                    'notes' => '5 rim rusak terkena air',
                ],
            ],
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'PENDING_APPROVAL');

        $opnameId = $createRes->json('id');

        // Stock shouldn't be updated yet while status is PENDING_APPROVAL
        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 50,
        ]);

        // 2. Owner approves the stock opname
        Sanctum::actingAs($this->ownerUser);

        $approveRes = $this->postJson("/api/v1/stock-opnames/{$opnameId}/approve");

        $approveRes->assertStatus(200)
            ->assertJsonPath('opname.status', 'COMPLETED');

        // Now stock is updated to physical_qty (45)
        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 45,
        ]);

        // Stock mutation for adjustment is recorded
        $this->assertDatabaseHas('stock_mutations', [
            'origin_warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'type' => 'OUT',
            'qty' => 5,
        ]);
    }

    public function test_low_stock_rop_alert_endpoint(): void
    {
        // Stock 20 is below ROP (reorder_point = 25)
        WarehouseStock::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'material_id' => $this->material->id,
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        Sanctum::actingAs($this->ownerUser);

        $response = $this->getJson('/api/v1/materials/low-stock');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.material_id', $this->material->id);
    }
}
