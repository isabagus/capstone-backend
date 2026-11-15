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
        Schema::create('spk_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_id')->constrained('spks')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();

            // Asal Bahan (Bahan Pabrik / Bahan Konsumen / Stok Gudang)
            $table->enum('source_type', [
                'BAHAN_SENDIRI',
                'BAHAN_KONSUMEN',
                'GUDANG'
            ])->default('BAHAN_SENDIRI');

            // Nama & Peruntukan Bahan
            $table->string('material_name', 150); // Contoh: "AP 100gr (65x100)"
            $table->string('component_name', 100)->nullable(); // Contoh: "Cover Luar", "Greyboard Kerangka", "Lining"

            // Kalkulasi Plano & Potongan Teknis Percetakan
            $table->unsignedInteger('plano_sheets')->default(0); // Jumlah bahan plano asal (cth: 575)
            $table->unsignedInteger('cuts_per_plano')->default(1); // Dipotong jadi (cth: 4)
            $table->unsignedInteger('cut_sheets')->default(0); // Jumlah lembar jadi potong (cth: 2300)

            // Ukuran Dimensi (cm)
            $table->string('cut_size', 50)->nullable(); // Ukuran jadi potong (cth: "43x30")
            $table->string('file_size', 50)->nullable(); // Ukuran file cetak desain (cth: "42x28.2")
            $table->string('target_product', 200)->nullable(); // Untuk cetakan (cth: "GFB ENREKANG BEANS 200GR...")

            // Layout 2D Skema Potongan
            $table->json('cutting_layout_schema')->nullable();

            // Kuantitas Pengeluaran & Gudang Asal
            $table->decimal('total_quantity', 10, 2)->default(0.00);
            $table->string('unit', 30)->default('Lembar');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_materials');
    }
};
