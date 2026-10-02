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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('unit', 20); // Plano, Rim, Lembar, Kg
            $table->decimal('safety_stock', 10, 2)->default(0.00);
            $table->decimal('reorder_point', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // Many-to-many pivot: material <-> brand
        Schema::create('material_brand', function (Blueprint $table) {
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->primary(['material_id', 'brand_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_brand');
        Schema::dropIfExists('materials');
    }
};
