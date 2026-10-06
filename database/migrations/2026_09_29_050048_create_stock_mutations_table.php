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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('mutation_code', 50)->unique();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('origin_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('qty', 10, 2);
            $table->string('type', 20); // IN | OUT | TRANSFER | ADJUSTMENT
            $table->string('reference_type', 50); // PO_INBOUND | SPK_OUTBOUND | WAREHOUSE_TRANSFER | OPNAME_ADJUSTMENT
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Index for audit queries
            $table->index(['material_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
