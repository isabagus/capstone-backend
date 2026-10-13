<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            /**
             * item_type distinguishes how the frontend categorises this record.
             * - 'material'  → Bahan Baku Utama & Consumables (Materials page)
             * - 'product'   → Barang Jadi / Setengah Jadi (Products page)
             * - 'sparepart' → Spare Part & suku cadang mesin (Spareparts page)
             *
             * This avoids creating separate tables and mirrors the frontend's
             * StockItemKind type ("material" | "product" | "sparepart").
             */
            $table->string('item_type', 20)->default('material')->after('sku');

            /** FK to supplier — the primary supplier for this material. */
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('reorder_point')
                ->constrained('suppliers')
                ->nullOnDelete();

            /** Paper gramature in GSM (e.g. 300 for Ivory 300 GSM). */
            $table->unsignedSmallInteger('gramature')->nullable()->after('supplier_id');

            /** Paper cut dimensions (e.g. "79 x 109 cm"). */
            $table->string('dimension', 50)->nullable()->after('gramature');

            /**
             * For Spareparts: the specific machine this part belongs to
             * (e.g. "Heidelberg SM74", "Polar 115").
             */
            $table->string('machine', 100)->nullable()->after('dimension');

            // Add category_id that was missing from original migration
            // (previously only referenced via unit_id but category FK was absent)
            if (! Schema::hasColumn('materials', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('item_type')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
        });

        // Index on item_type for fast filtering per page
        Schema::table('materials', function (Blueprint $table) {
            $table->index('item_type');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['item_type']);
            $table->dropIndex(['supplier_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['item_type', 'supplier_id', 'gramature', 'dimension', 'machine']);
        });
    }
};
