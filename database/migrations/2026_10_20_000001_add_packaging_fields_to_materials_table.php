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
        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('materials', 'gramature')) {
                $table->decimal('gramature', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('materials', 'dimension')) {
                $table->string('dimension', 100)->nullable();
            }
            if (!Schema::hasColumn('materials', 'batch_no')) {
                $table->string('batch_no', 100)->nullable();
            }
            if (!Schema::hasColumn('materials', 'expiry_date')) {
                $table->date('expiry_date')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'gramature', 'dimension', 'batch_no', 'expiry_date']);
        });
    }
};
