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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('type', 30)->default('Barang Jadi'); // Barang Jadi, Setengah Jadi
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unit', 20)->default('Pcs');
            $table->decimal('min_stock', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('qty_available', 10, 2)->default(0.00);
            $table->decimal('qty_reserved', 10, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
    }
};
