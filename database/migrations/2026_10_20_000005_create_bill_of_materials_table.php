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
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->string('bom_code', 64)->unique();
            $table->string('order_number', 64)->nullable()->index();
            $table->string('customer_name', 150)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('product_name', 200)->nullable();
            
            // Relasi bahan baku kertas jika terhubung
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('paper_type', 150)->default('Kertas Ivory 300 GSM');
            $table->decimal('paper_gsm', 8, 2)->default(300);

            // Dimensi Plano & Potong (cm)
            $table->decimal('plano_length_cm', 8, 2)->default(109);
            $table->decimal('plano_width_cm', 8, 2)->default(79);
            $table->decimal('cut_length_cm', 8, 2)->default(38);
            $table->decimal('cut_width_cm', 8, 2)->default(26);

            // Hasil Cutting Layout 2D
            $table->unsignedInteger('order_quantity')->default(1000);
            $table->unsignedInteger('cuts_per_plano')->default(1);
            $table->string('orientation', 100)->default('Kombinasi Optimal');
            $table->jsonb('layout_pieces')->nullable(); // Koordinat [{id, x, y, w, h, orientation}]

            // Toleransi & Insheet
            $table->decimal('waste_percent', 5, 2)->default(5.0);
            $table->unsignedInteger('insheet_quantity')->default(100);

            // Kalkulasi Hasil Akumulasi
            $table->unsignedInteger('net_plano_sheets')->default(0);
            $table->unsignedInteger('waste_plano_sheets')->default(0);
            $table->unsignedInteger('total_plano_sheets')->default(0);
            $table->decimal('total_rims', 8, 2)->default(0.00);
            $table->decimal('total_weight_kg', 10, 2)->default(0.00);
            $table->decimal('efficiency_rate', 5, 2)->default(0.00);

            // Bahan Pendukung (Consumables)
            $table->decimal('ink_estimate_kg', 8, 2)->nullable();
            $table->decimal('glue_estimate_kg', 8, 2)->nullable();
            $table->decimal('foil_length_m', 8, 2)->nullable();

            // Status & Approval
            $table->enum('status', ['DRAFT', 'CALCULATED', 'APPROVED', 'CONVERTED_TO_SPK'])->default('CALCULATED');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
