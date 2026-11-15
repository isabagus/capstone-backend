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
        Schema::create('spks', function (Blueprint $table) {
            $table->id();
            $table->string('spk_number', 64)->unique()->index();
            $table->string('order_number', 64)->nullable()->index();
            $table->string('folder_name', 150)->nullable();
            $table->string('job_name', 200)->nullable();
            $table->string('customer_name', 150)->nullable();

            // Relasi Brand & BOM
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('bom_id')->nullable()->constrained('bill_of_materials')->nullOnDelete();

            // Tipe Pengerjaan: Internal vs Eksternal (Vendor Maklon)
            $table->enum('type', ['INTERNAL', 'EXTERNAL'])->default('INTERNAL');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            // Enum Product Type Label & Delivery Type (Sesuai Permintaan Spesifik User)
            $table->enum('product_type_label', [
                'ALUFOIL',
                'HARDBOX',
                'SOFTBOX',
                'CORRUGATED',
                'LABEL_STICKER',
                'OTHERS'
            ])->default('ALUFOIL');

            $table->enum('delivery_type', [
                'DIAMBIL',
                'DIKIRIM',
                'EKSPEDISI'
            ])->default('DIAMBIL');

            $table->string('receipt_number', 100)->nullable();
            $table->string('color_standard', 150)->default('Standart Martin / Indicator');
            $table->string('packing_type', 100)->default('Bungkus');
            $table->unsignedInteger('order_quantity')->default(1000);

            // Waktu & Batas Pengerjaan
            $table->date('publish_date')->nullable()->index();
            $table->dateTime('deadline')->nullable()->index();

            // Status SPK
            $table->enum('status', [
                'DRAFT',
                'TERBIT',
                'IN_PRODUCTION',
                'SELESAI',
                'CANCELLED'
            ])->default('DRAFT');

            // Detail Tambahan Finishing & Rincian Biaya (JSON)
            $table->json('finishing_details')->nullable();
            $table->json('cost_breakdown')->nullable();
            $table->text('notes')->nullable();

            // Tanda Tangan / Checker Pengesahan Dokumen
            $table->foreignId('layouter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checker1_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checker2_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spks');
    }
};
