<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // Part number mesin
            $table->string('name', 150);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unit', 20)->default('Pcs');
            $table->string('machine', 100)->nullable(); // Heidelberg SM74, Polar 115, etc.
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('safety_stock', 10, 2)->default(0.00);
            $table->decimal('reorder_point', 10, 2)->default(0.00);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('sparepart_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sparepart_id')->constrained('spareparts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('qty_available', 10, 2)->default(0.00);
            $table->decimal('qty_reserved', 10, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['sparepart_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sparepart_stocks');
        Schema::dropIfExists('spareparts');
    }
};
